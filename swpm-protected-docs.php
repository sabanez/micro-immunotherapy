<?php
/**
 * Plugin Name: Protección de documentos (SWPM)
 * Description: Exige sesión de socio activa (Simple WordPress Membership) para
 *              descargar los documentos indicados en el array $protegidos,
 *              sin necesidad de moverlos de la carpeta de subidas habitual.
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Acceso directo no permitido.
}

/**
 * Array de documentos a proteger.
 * Indica la ruta RELATIVA dentro de wp-content/uploads/, tal como aparece
 * en la URL del archivo cuando el gestor de contenido lo sube normalmente.
 *
 * Ejemplo: si la URL del PDF es
 *   https://tudominio.com/wp-content/uploads/2026/03/manual-interno.pdf
 * la entrada correspondiente es '2026/03/manual-interno.pdf'.
 */
function labolife_swpm_protected_documents() {
    return array(        
        // Documents and Articles / Scientific Studies
        '2019/12/Immunity-inflammation-and-Micro-Immunotherapy-2016.pdf',
        '2019/12/Mitochondrial-regulation-and-micro-immunotherapy.pdf',
        '2019/12/Stress-and-aging-the-microimmunotherapy-approach-2023.pdf',
        '2020/02/Allergies-and-MI.pdf',
        '2020/03/EBV-infections-and-MI.pdf',
        '2020/03/MI-and-cancer.pdf',
        '2020/04/DocOnco_2020_vFinale_web.pdf',
        '2020/04/WellAginMI_A4_EN_2019_vWeb.pdf',
        '2020/11/news112020_INTERNACIONAL_Noviembre_2020_Articulo_M._Peiré_OK.pdf',
        '2020/11/news112020_INTERNACIONAL_Noviembre_2020_Benefits-of-using-vitamin-C-and-micro-immunotherapy-in-the-management-of-infections_OK.pdf',
        '2020/11/news112020_INTERNACIONAL_Noviembre_2020_SEBV-and-Other-Herpes-Viruses_OK.pdf',
        '2020/12/DocCompilePAPI_EN_web-2.pdf',
        '2021/02/Journal-of-Periodontology-article.pdf',
        '2021/02/news022021_INTERNACIONAL_Febrero_2021.pdf',
        '2021/05/Micro-immunotherapy.pdf',
        '2021/05/Newsletter-May-2021.pdf',
        '2021/07/Autoimmune-thyroiditis-Case-report.pdf',
        '2021/07/MI-Post-COVID-Syndrome.pdf',
        '2021/11/Sports-medicine-Micro-immuntherapy.pdf',
        '2022/01/news012022_january_2022.pdf',
        '2022/05/Type-2-Diabetes-Immune-Disorders-and-Infections-Cause-or-Consequence_.pdf',
        '2022/07/Mitochondrial-Health-and-COVID-19-Benefits-of-the-Formula-MIREG.pdf',
        '2022/09/Interpretation-of-lymphocyte-typing.pdf',
        '2022/12/Cellular-senescence-and-COVID-19-Benefits-of-the-formula-MISEN-1.pdf',
        '2022/12/Chronic-inflammation-and-micro-immunotherapy.pdf',
        '2023/04/CaseReport_LongCovidwithHyperinflammation_INTERNACIONAL-v4.pdf',
        '2023/04/Chronic-inflammation-and-micro-immunotherapy-v3.pdf',
        '2023/06/CaseReport_Systematic_Lupus_Erythematosus.pdf',
        '2023/08/microimmunotherapy-in-veterinary-practice.pdf',
        '2023/12/DOC_LEAKY_GUT_2023C1.pdf',
        '2023/12/DOC_UTIS_2023-C.pdf',
        '2025/01/Advanced-micro-immunotherapy-handbook.pdf',
        '2025/01/DOC_WOMENSHEALTH_EN_v2025.pdf',
        '2025/01/Get-started-with-micro-immunotherapy-Handbook-.pdf',
        '2025/01/Micro-immunotherapy-Anogenital-Human-Papillomavirus-infections.pdf',
        '2025/01/Micro-immunotherapy-Cytomegalovirus-Infections.pdf',
        '2025/01/Micro-immunotherapy-Epstein-Barr-Virus-Infections.pdf',
        '2025/01/Micro-immunotherapy-Herpes-Simplex-Virus-Infections.pdf',
        '2025/01/Micro-immunotherapy-Varicella-zoster-virus-infection-version-diciembre-2025.pdf',
        '2026/08/Micro-immunotherapy-Infections-Strengthening-natural-defences-Agosto-2026.pdf',
        '2026/09/Ficha_PAPI_INT_WEB.pdf',
    );
}

/**
 * Tras un registro correcto (sin activación por email), si la petición trae
 * el parámetro swpm_redirect_to (lo añadimos nosotros al redirigir a la
 * página de login/registro), llevar al socio directamente al documento que
 * quería consultar en vez de a la URL fija configurada en SWPM.
 */
add_filter( 'swpm_after_registration_redirect_url', 'labolife_swpm_after_registration_redirect_url' );
function labolife_swpm_after_registration_redirect_url( $default_url ) {
    if ( empty( $_REQUEST['swpm_redirect_to'] ) ) {
        return $default_url;
    }
    $redirect_to = esc_url_raw( wp_unslash( $_REQUEST['swpm_redirect_to'] ) );
    return ! empty( $redirect_to ) ? $redirect_to : $default_url;
}

/**
 * La página de login (professional-area) tiene un botón "Register" que enlaza
 * de forma estática a la página de registro, sin arrastrar query string. Si el
 * visitante llegó al login con swpm_redirect_to (porque quería un documento
 * protegido) pero no tiene cuenta, hay que añadirle el mismo parámetro a ese
 * enlace para que, tras registrarse, también acabe en el documento.
 */
add_action( 'wp_footer', 'labolife_swpm_propagate_redirect_to_register_link' );
function labolife_swpm_propagate_redirect_to_register_link() {
    if ( empty( $_GET['swpm_redirect_to'] ) ) {
        return;
    }
    $redirect_to = esc_url_raw( wp_unslash( $_GET['swpm_redirect_to'] ) );
    if ( empty( $redirect_to ) ) {
        return;
    }
    ?>
    <script>
    (function () {
        var redirectTo = <?php echo wp_json_encode( $redirect_to ); ?>;
        var link = document.querySelector('a[href*="professional-area-registration"]');
        if ( link ) {
            var url = new URL( link.href, window.location.origin );
            url.searchParams.set( 'swpm_redirect_to', redirectTo );
            link.href = url.toString();
        }
    })();
    </script>
    <?php
}

add_action( 'init', 'labolife_swpm_guard_protected_uploads', 1 );

function labolife_swpm_guard_protected_uploads() {

    $request_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
    if ( ! $request_path ) {
        return;
    }

    $uploads_marker = '/wp-content/uploads/';
    $marker_pos     = strpos( $request_path, $uploads_marker );

    // Esta petición no es a un archivo dentro de wp-content/uploads: no hacer nada.
    if ( false === $marker_pos ) {
        return;
    }

    $relative = ltrim( substr( $request_path, $marker_pos + strlen( $uploads_marker ) ), '/' );
    $relative = rawurldecode( $relative );

    // Evitar cualquier intento de salir de la carpeta de subidas (../../..)
    if ( false !== strpos( $relative, '..' ) ) {
        wp_die( 'Petición no válida.', 'Error', array( 'response' => 400 ) );
    }

    $ruta_absoluta = WP_CONTENT_DIR . '/uploads/' . $relative;

    // Si el archivo no existe físicamente, dejar que WordPress siga su curso normal (404 estándar, etc.)
    if ( ! file_exists( $ruta_absoluta ) || is_dir( $ruta_absoluta ) ) {
        return;
    }

    $protegidos = labolife_swpm_protected_documents();
    $es_protegido = in_array( $relative, $protegidos, true );

    if ( $es_protegido ) {
        $logueado = class_exists( 'SwpmMemberUtils' ) && SwpmMemberUtils::is_member_logged_in();

        if ( ! $logueado ) {
            $doc_url   = home_url( $request_path );
            $login_url = 'https://www.micro-immunotherapy.com/professional-area/?swpm_redirect_to=' . urlencode( $doc_url );
            wp_safe_redirect( $login_url );
            exit;
        }
    }

    // Llegados aquí: o el documento no está en la lista de protegidos (se sirve
    // con normalidad, igual que antes), o está protegido y el socio tiene sesión válida.
    $mime = function_exists( 'mime_content_type' ) ? mime_content_type( $ruta_absoluta ) : 'application/octet-stream';

    header( 'Content-Type: ' . $mime );
    header( 'Content-Disposition: inline; filename="' . basename( $ruta_absoluta ) . '"' );
    header( 'Content-Length: ' . filesize( $ruta_absoluta ) );

    if ( $es_protegido ) {
        header( 'Cache-Control: private, no-store' );
        header( 'X-Robots-Tag: noindex, nofollow' );
    }

    readfile( $ruta_absoluta );
    exit;
}