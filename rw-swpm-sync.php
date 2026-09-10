<?php
/**
 * SWPM -> WooCommerce sync on member create/update/login.
 * Self contained to avoid theme load order issues.
 */

// Hooks fired by Simple Membership when a member is created/updated on the front.
add_action( 'swpm_registration_complete', 'rw_swpm_sync_member_to_wc', 10, 1 );
add_action( 'swpm_front_end_registration_complete', 'rw_swpm_sync_member_to_wc', 10, 1 );
add_action( 'swpm_member_profile_updated', 'rw_swpm_sync_member_to_wc', 10, 1 );
// When admin changes account status (e.g., pending -> active).
add_action( 'swpm_admin_account_status_updated', function( $info ) {
    if ( ! empty( $info['member_id'] ) ) {
        rw_swpm_sync_member_to_wc( intval( $info['member_id'] ) );
    }
}, 10, 1 );
add_action( 'swpm_account_status_updated', function( $member_id ) {
    if ( ! empty( $member_id ) ) {
        rw_swpm_sync_member_to_wc( intval( $member_id ) );
    }
}, 10, 1 );

// Also sync at WP login, in case profile existed before we added this bridge.
add_action( 'wp_login', function( $user_login, $user ) {
    if ( empty( $user->user_email ) ) {
        return;
    }
    $member = rw_swpm_get_member_by_email_local( $user->user_email );
    if ( $member ) {
        rw_swpm_sync_member_to_wc( $member['member_id'], $user->ID );
    }
}, 10, 2 );

/**
 * Main sync routine.
 *
 * @param int      $member_id  SWPM member id.
 * @param int|null $wp_user_id Optional WP user id (if known).
 */
function rw_swpm_sync_member_to_wc( $member_id, $wp_user_id = null ) {
    if ( empty( $member_id ) ) {
        return;
    }

    $member_row    = rw_swpm_get_member_row( $member_id );
    $custom_fields = rw_swpm_get_custom_fields_by_member_local( $member_id );
    if ( ! $member_row ) {
        return;
    }

    // Hydrate SWPM base address fields from custom fields to keep SWPM consistent.
    $member_row = rw_swpm_hydrate_member_address_from_custom( $member_row, $custom_fields, true );

    // Resolve WP user by email if not provided.
    if ( empty( $wp_user_id ) ) {
        $user = get_user_by( 'email', $member_row['email'] );
        if ( ! $user ) {
            $wp_user_id = rw_swpm_create_wp_user_from_member_local( $member_row );
            if ( ! $wp_user_id ) {
                return; // Could not safely create WP user; cannot sync.
            }
        } else {
            $wp_user_id = $user->ID;
        }
    }

    rw_swpm_sync_to_wc_user_meta_local( $wp_user_id, $member_row, $custom_fields );
}

/** Create the paired WP user for an SWPM member when it is missing. */
function rw_swpm_create_wp_user_from_member_local( array $member_row ) {
    global $wpdb;

    $email = isset( $member_row['email'] ) ? trim( $member_row['email'] ) : '';
    $login = isset( $member_row['user_name'] ) ? trim( $member_row['user_name'] ) : '';

    if ( empty( $email ) || empty( $login ) || ! is_email( $email ) ) {
        return 0;
    }

    if ( email_exists( $email ) || username_exists( $login ) ) {
        return 0;
    }

    $display_name = trim( trim( $member_row['first_name'] ?? '' ) . ' ' . trim( $member_row['last_name'] ?? '' ) );

    $user_id = wp_insert_user( array(
        'user_login'   => $login,
        'user_pass'    => wp_generate_password( 32, true, true ),
        'user_email'   => $email,
        'first_name'   => $member_row['first_name'] ?? '',
        'last_name'    => $member_row['last_name'] ?? '',
        'display_name' => $display_name ? $display_name : $login,
        'role'         => 'subscriber',
    ) );

    if ( is_wp_error( $user_id ) ) {
        if ( function_exists( 'error_log' ) ) {
            error_log( 'RW SWPM sync: could not create WP user for member ' . ( $member_row['member_id'] ?? '' ) . ': ' . $user_id->get_error_message() );
        }
        return 0;
    }

    if ( ! empty( $member_row['password'] ) ) {
        $wpdb->update(
            $wpdb->users,
            array( 'user_pass' => $member_row['password'] ),
            array( 'ID' => $user_id ),
            array( '%s' ),
            array( '%d' )
        );
        clean_user_cache( $user_id );
    }

    return (int) $user_id;
}

/** Fetch SWPM member row by email. */
function rw_swpm_get_member_by_email_local( $email ) {
    global $wpdb;
    if ( empty( $email ) ) {
        return null;
    }
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}swpm_members_tbl WHERE email = %s", $email ), ARRAY_A );
}

/** Fetch SWPM member row by id. */
function rw_swpm_get_member_row( $member_id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}swpm_members_tbl WHERE member_id = %d", $member_id ), ARRAY_A );
}

/** Map SWPM form-builder custom fields for a member. */
function rw_swpm_get_custom_fields_by_member_local( $member_id ) {
    global $wpdb;
    $table = "{$wpdb->prefix}swpm_form_builder_custom";
    $rows  = $wpdb->get_results( $wpdb->prepare( "SELECT field_id, value FROM {$table} WHERE user_id = %d", $member_id ), ARRAY_A );
    $map   = array();
    foreach ( $rows as $row ) {
        $map[ intval( $row['field_id'] ) ] = $row['value'];
    }
    return $map;
}

/** Hydrate SWPM base address fields from custom field pairs; optionally persist back to table. */
function rw_swpm_hydrate_member_address_from_custom( array $member_row, array $cf, $persist = false ) {
    global $wpdb;

    $street  = $cf[378] ?? $cf[377] ?? '';
    $city    = $cf[380] ?? $cf[379] ?? '';
    $zip     = $cf[382] ?? $cf[381] ?? '';
    $country = $cf[386] ?? $cf[385] ?? '';

    $updates = array();
    if ( empty( $member_row['address_street'] ) && $street ) {
        $member_row['address_street'] = $street;
        $updates['address_street']    = $street;
    }
    if ( empty( $member_row['address_city'] ) && $city ) {
        $member_row['address_city'] = $city;
        $updates['address_city']    = $city;
    }
    if ( empty( $member_row['address_zipcode'] ) && $zip ) {
        $member_row['address_zipcode'] = $zip;
        $updates['address_zipcode']    = $zip;
    }
    if ( empty( $member_row['country'] ) && $country ) {
        $member_row['country'] = $country;
        $updates['country']    = $country;
    }

    if ( $persist && ! empty( $updates ) ) {
        $wpdb->update( $wpdb->prefix . 'swpm_members_tbl', $updates, array( 'member_id' => $member_row['member_id'] ) );
    }

    return $member_row;
}

/** Write mapped fields into Woo user meta. */
function rw_swpm_sync_to_wc_user_meta_local( $user_id, $member_row, $custom_fields ) {
    if ( ! $user_id ) {
        return;
    }
    // Field IDs from SWPM form: street=378/377, city=380/379, postcode=382/381, country=386/385.
    $country_map   = array();
    if ( function_exists( 'countryArray' ) ) {
        $country_map = countryArray();
    } elseif ( class_exists( 'WC_Countries' ) ) {
        $country_map = ( new WC_Countries() )->countries;
    }

    $country_value = $custom_fields[386] ?? $custom_fields[385] ?? ( $member_row['country'] ?? '' );
    $country_code  = $country_value ? array_search( $country_value, $country_map, true ) : false;
    if ( false === $country_code && isset( $country_map[ $country_value ] ) ) {
        $country_code = $country_value; // already ISO
    }

    $meta_map = array(
        'billing_first_name' => $member_row['first_name'] ?? '',
        'billing_last_name'  => $member_row['last_name'] ?? '',
        'billing_phone'      => $member_row['phone'] ?? '',
        'billing_address_1'  => $custom_fields[378] ?? $custom_fields[377] ?? ( $member_row['address_street'] ?? '' ),
        'billing_city'       => $custom_fields[380] ?? $custom_fields[379] ?? ( $member_row['address_city'] ?? '' ),
        'billing_state'      => $member_row['address_state'] ?? '',
        'billing_postcode'   => $custom_fields[382] ?? $custom_fields[381] ?? ( $member_row['address_zipcode'] ?? '' ),
        'billing_country'    => $country_code ? $country_code : '',
    );

    foreach ( $meta_map as $meta_key => $value ) {
        if ( $value === '' ) {
            continue;
        }
        $current = get_user_meta( $user_id, $meta_key, true );
        if ( $current !== $value ) {
            update_user_meta( $user_id, $meta_key, $value );
        }
    }
}
