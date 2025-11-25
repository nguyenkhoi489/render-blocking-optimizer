<?php
/**
 * Plugin Name: Render Blocking Optimizer
 * Plugin URI: https://yoursite.com
 * Description: Tối ưu CSS và JavaScript để giảm render blocking và cải thiện tốc độ tải trang
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * Text Domain: render-blocking-optimizer
 */

// Ngăn truy cập trực tiếp
if (!defined('ABSPATH')) {
    exit;
}

class Render_Blocking_Optimizer {
    
    private $options;
    
    public function __construct() {
        // Khởi tạo options với giá trị mặc định
        $default_options = array(
            'defer_js' => 1,
            'async_css' => 1,
            'inline_critical_css' => 0,
            'exclude_jquery' => 0,
            'exclude_jquery_urls' => '',
            'exclude_scripts' => '',
            'exclude_styles' => ''
        );
        
        $saved_options = get_option('rbo_settings', array());
        $this->options = wp_parse_args($saved_options, $default_options);
        
        // Hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Tối ưu frontend
        if (!is_admin()) {
            add_filter('script_loader_tag', array($this, 'optimize_scripts'), 10, 3);
            add_filter('style_loader_tag', array($this, 'optimize_styles'), 10, 4);
            add_action('wp_head', array($this, 'add_preload_tags'), 1);
            add_filter('style_loader_tag', array($this, 'optimize_google_fonts'), 999, 4);
            
            // Disable Google Fonts nếu có local fonts
            add_action('wp_enqueue_scripts', array($this, 'maybe_disable_google_fonts'), 99999);
        }
    }
    
    /**
     * Thêm menu trong admin
     */
    public function add_admin_menu() {
        add_options_page(
            'Render Blocking Optimizer',
            'RB Optimizer',
            'manage_options',
            'render-blocking-optimizer',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Đăng ký settings
     */
    public function register_settings() {
        register_setting('rbo_settings_group', 'rbo_settings', array($this, 'sanitize_settings'));
    }
    
    /**
     * Sanitize và xử lý settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Checkbox values - mặc định là 0 nếu không được check
        $sanitized['defer_js'] = isset($input['defer_js']) ? 1 : 0;
        $sanitized['async_css'] = isset($input['async_css']) ? 1 : 0;
        $sanitized['inline_critical_css'] = isset($input['inline_critical_css']) ? 1 : 0;
        $sanitized['exclude_jquery'] = isset($input['exclude_jquery']) ? 1 : 0;
        
        // Text fields
        $sanitized['exclude_jquery_urls'] = isset($input['exclude_jquery_urls']) ? sanitize_textarea_field($input['exclude_jquery_urls']) : '';
        $sanitized['exclude_scripts'] = isset($input['exclude_scripts']) ? sanitize_text_field($input['exclude_scripts']) : '';
        $sanitized['exclude_styles'] = isset($input['exclude_styles']) ? sanitize_text_field($input['exclude_styles']) : '';
        
        return $sanitized;
    }
    
    /**
     * Tối ưu JavaScript tags
     */
    public function optimize_scripts($tag, $handle, $src) {
        // Kiểm tra nếu defer được bật
        if (empty($this->options['defer_js'])) {
            return $tag;
        }
        
        // Loại trừ jQuery nếu được chọn (globally)
        if (!empty($this->options['exclude_jquery']) && strpos($handle, 'jquery') !== false) {
            return $tag;
        }
        
        // Loại trừ jQuery theo URL cụ thể
        if (!empty($this->options['exclude_jquery_urls']) && strpos($handle, 'jquery') !== false) {
            if ($this->should_exclude_jquery_by_url()) {
                return $tag;
            }
        }
        
        // Loại trừ scripts cụ thể
        $exclude_list = array_filter(array_map('trim', explode(',', $this->options['exclude_scripts'])));
        foreach ($exclude_list as $exclude) {
            if (strpos($handle, $exclude) !== false || strpos($src, $exclude) !== false) {
                return $tag;
            }
        }
        
        // Thêm defer nếu chưa có
        if (strpos($tag, 'defer') === false && strpos($tag, 'async') === false) {
            $tag = str_replace(' src', ' defer src', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Kiểm tra xem có nên loại trừ jQuery theo URL không
     */
    private function should_exclude_jquery_by_url() {
        if (empty($this->options['exclude_jquery_urls'])) {
            return false;
        }
        
        // Lấy URL hiện tại
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        
        // Lấy danh sách URL cần loại trừ (mỗi URL một dòng)
        $exclude_urls = array_filter(array_map('trim', explode("\n", $this->options['exclude_jquery_urls'])));
        
        foreach ($exclude_urls as $exclude_url) {
            // Chuẩn hóa URL để so sánh
            $exclude_url = trim($exclude_url);
            
            // Bỏ qua dòng trống
            if (empty($exclude_url)) {
                continue;
            }
            
            // Kiểm tra nếu URL hiện tại chứa URL cần loại trừ
            // Hỗ trợ cả URL đầy đủ và partial URL
            if (strpos($current_url, $exclude_url) !== false) {
                return true;
            }
            
            // Kiểm tra theo request URI (không có domain)
            if (strpos($_SERVER['REQUEST_URI'], str_replace($_SERVER['HTTP_HOST'], '', str_replace(['http://', 'https://'], '', $exclude_url))) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Tối ưu CSS tags
     */
    public function optimize_styles($html, $handle, $href, $media) {
        // Kiểm tra nếu async CSS được bật
        if (empty($this->options['async_css'])) {
            return $html;
        }
        
        // Danh sách CSS quan trọng của Flatsome và các theme phổ biến - KHÔNG async
        $critical_styles = array(
            'flatsome-style',           // CSS chính của Flatsome
            'flatsome-base-css',        // Base CSS
            'flatsome-shop',            // WooCommerce styles
            'woocommerce-layout',       // WooCommerce layout
            'woocommerce-smallscreen',  // Responsive WooCommerce
            'woocommerce-general',      // WooCommerce general
            'wp-block-library',         // Gutenberg blocks
            'global-styles',            // Global styles
        );
        
        // Kiểm tra nếu là CSS quan trọng
        foreach ($critical_styles as $critical) {
            if (strpos($handle, $critical) !== false) {
                return $html;
            }
        }
        
        // Loại trừ styles cụ thể từ settings
        $exclude_list = array_filter(array_map('trim', explode(',', $this->options['exclude_styles'])));
        foreach ($exclude_list as $exclude) {
            if (strpos($handle, $exclude) !== false || strpos($href, $exclude) !== false) {
                return $html;
            }
        }
        
        // Chỉ async các CSS không quan trọng
        $html = str_replace("media='all'", "media='print' onload=\"this.media='all'\"", $html);
        $html = str_replace('media="all"', 'media="print" onload="this.media=\'all\'"', $html);
        
        // Thêm noscript fallback
        $noscript = '<noscript><link rel="stylesheet" href="' . esc_url($href) . '" media="all"></noscript>';
        $html .= $noscript;
        
        return $html;
    }
    
    /**
     * Thêm preload tags cho tài nguyên quan trọng
     */
    public function add_preload_tags() {
        global $wp_styles, $wp_scripts;
        
        // === PRELOAD LOCAL FONTS FROM THEME ===
        $theme = wp_get_theme(get_template());
        $version = $theme->get('Version');
        $fonts_url = get_template_directory_uri() . '/assets/css/icons';
        
        // Preload Flatsome icon font (woff2 - format hiện đại nhất)
        echo '<link rel="preload" href="' . esc_url($fonts_url . '/fl-icons.woff2?v=' . $version) . '" as="font" type="font/woff2" crossorigin>' . "\n";
        
        // Preload woff fallback cho browser cũ
        echo '<link rel="preload" href="' . esc_url($fonts_url . '/fl-icons.woff?v=' . $version) . '" as="font" type="font/woff" crossorigin>' . "\n";
        
        // === PRELOAD LOCAL FONTS FROM /wp-content/fonts/ ===
        $local_fonts_dir = WP_CONTENT_DIR . '/fonts';
        $local_fonts_url = content_url('/fonts');
        $has_local_fonts = false;
        $local_font_families = array();
        
        if (is_dir($local_fonts_dir)) {
            // Scan thư mục fonts bao gồm cả thư mục con để tìm woff2 và woff files
            $font_extensions = array('woff2', 'woff');
            $found_fonts = array();
            
            // Sử dụng RecursiveDirectoryIterator để scan tất cả thư mục con
            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($local_fonts_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $extension = strtolower($file->getExtension());
                        if (in_array($extension, $font_extensions)) {
                            $relative_path = str_replace($local_fonts_dir . '/', '', $file->getPathname());
                            $found_fonts[$extension][] = $relative_path;
                            
                            // Lấy tên thư mục font family (lato, roboto, etc.)
                            $path_parts = explode('/', $relative_path);
                            if (count($path_parts) > 1) {
                                $font_family = strtolower($path_parts[0]);
                                $local_font_families[] = $font_family;
                            }
                        }
                    }
                }
                
                $local_font_families = array_unique($local_font_families);
                
                // Preload woff2 files (ưu tiên format hiện đại)
                if (!empty($found_fonts['woff2'])) {
                    $has_local_fonts = true;
                    // Giới hạn preload 4-6 fonts quan trọng nhất để tránh quá nhiều request
                    $fonts_to_preload = array_slice($found_fonts['woff2'], 0, 6);
                    
                    foreach ($fonts_to_preload as $font_path) {
                        $font_url = $local_fonts_url . '/' . $font_path;
                        echo '<link rel="preload" href="' . esc_url($font_url) . '" as="font" type="font/woff2" crossorigin>' . "\n";
                    }
                }
                
            } catch (Exception $e) {
                // Nếu có lỗi khi scan, bỏ qua và tiếp tục
                error_log('Font preload error: ' . $e->getMessage());
            }
        }
        
        // === PRELOAD GOOGLE FONTS (Chỉ khi KHÔNG có local fonts tương ứng) ===
        // Lấy thông tin fonts từ theme settings
        $type_headings = get_theme_mod('type_headings', array('font-family' => 'Lato', 'variant' => '700'));
        $type_texts = get_theme_mod('type_texts', array('font-family' => 'Lato', 'variant' => 'regular'));
        $type_nav = get_theme_mod('type_nav', array('font-family' => 'Lato', 'variant' => '700'));
        $type_alt = get_theme_mod('type_alt', array('font-family' => 'Dancing Script', 'variant' => 'regular'));
        
        // Map các font phổ biến với URL woff2 của chúng từ Google Fonts
        $font_urls = array(
            'Lato' => array(
                'regular' => 'https://fonts.gstatic.com/s/lato/v24/S6uyw4BMUTPHjx4wXiWtFCc.woff2',
                '700' => 'https://fonts.gstatic.com/s/lato/v24/S6u9w4BMUTPHh6UVSwiPGQ3q5d0.woff2',
            ),
            'Roboto' => array(
                'regular' => 'https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Me5WZLCzYlKw.woff2',
                '700' => 'https://fonts.gstatic.com/s/roboto/v30/KFOlCnqEu92Fr1MmWUlfBBc9.woff2',
            ),
            'Open Sans' => array(
                'regular' => 'https://fonts.gstatic.com/s/opensans/v34/memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsjZ0B4gaVI.woff2',
                '700' => 'https://fonts.gstatic.com/s/opensans/v34/memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsg-1x4gaVI.woff2',
            ),
            'Montserrat' => array(
                'regular' => 'https://fonts.gstatic.com/s/montserrat/v25/JTUSjIg1_i6t8kCHKm459WlhyyTh89Y.woff2',
                '700' => 'https://fonts.gstatic.com/s/montserrat/v25/JTUSjIg1_i6t8kCHKm459Wlhyw3JAvxQ.woff2',
            ),
            'Poppins' => array(
                'regular' => 'https://fonts.gstatic.com/s/poppins/v20/pxiEyp8kv8JHgFVrJJfecnFHGPc.woff2',
                '700' => 'https://fonts.gstatic.com/s/poppins/v20/pxiByp8kv8JHgFVrLCz7Z1xlFQ.woff2',
            ),
            'Dancing Script' => array(
                'regular' => 'https://fonts.gstatic.com/s/dancingscript/v24/If2cXTr6YS-zF4S-kcSWSVi_sxjsohD9F50Ruu7BMSo3Sup8.woff2',
            ),
        );
        
        // Preload các fonts được sử dụng trong theme (CHỈ KHI KHÔNG CÓ LOCAL FONT)
        $used_fonts = array($type_headings, $type_texts, $type_nav, $type_alt);
        $preloaded = array(); // Tránh preload trùng
        
        foreach ($used_fonts as $font_config) {
            if (!is_array($font_config)) continue;
            
            $font_family = isset($font_config['font-family']) ? $font_config['font-family'] : '';
            $variant = isset($font_config['variant']) ? $font_config['variant'] : 'regular';
            
            // Chuẩn hóa variant
            if ($variant === 'default' || empty($variant)) {
                $variant = 'regular';
            }
            
            // Tạo key unique để tránh duplicate
            $font_key = $font_family . '-' . $variant;
            
            // KIỂM TRA: Chỉ preload Google Font nếu KHÔNG có local font tương ứng
            $font_family_lowercase = strtolower(str_replace(' ', '-', $font_family));
            $has_local_version = in_array($font_family_lowercase, $local_font_families) || 
                                 in_array(str_replace('-', '', $font_family_lowercase), $local_font_families);
            
            if ($font_family && isset($font_urls[$font_family][$variant]) && !in_array($font_key, $preloaded) && !$has_local_version) {
                echo '<link rel="preload" href="' . esc_url($font_urls[$font_family][$variant]) . '" as="font" type="font/woff2" crossorigin>' . "\n";
                $preloaded[] = $font_key;
            }
        }
        
        // === PRELOAD CRITICAL CSS ===
        $flatsome_critical_css = array(
            'flatsome-main',        // CSS chính - quan trọng nhất
            'flatsome-style',
            'flatsome-shop',
            'woocommerce-layout',
            'woocommerce-smallscreen',
            'woocommerce-general'
        );
        
        // Preload CSS quan trọng của Flatsome
        if (!empty($wp_styles->queue)) {
            foreach ($flatsome_critical_css as $handle) {
                if (in_array($handle, $wp_styles->queue) && isset($wp_styles->registered[$handle])) {
                    $src = $wp_styles->registered[$handle]->src;
                    // Thêm version nếu có
                    $ver = isset($wp_styles->registered[$handle]->ver) ? $wp_styles->registered[$handle]->ver : '';
                    $src = $ver ? add_query_arg('ver', $ver, $src) : $src;
                    echo '<link rel="preload" href="' . esc_url($src) . '" as="style">' . "\n";
                }
            }
        }
        
        // === RESOURCE HINTS ===
        // Preconnect cho Google Fonts (ưu tiên cao nhất)
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        
        // DNS-prefetch cho các domain khác
        echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//fonts.gstatic.com">' . "\n";
    }
    
    /**
     * Tối ưu Google Fonts - thêm display=swap
     */
    public function optimize_google_fonts($html, $handle, $href, $media) {
        // Kiểm tra nếu là Google Fonts
        if (strpos($href, 'fonts.googleapis.com') !== false) {
            // Thêm display=swap nếu chưa có
            if (strpos($href, 'display=') === false) {
                $separator = strpos($href, '?') !== false ? '&' : '?';
                $new_href = $href . $separator . 'display=swap';
                $html = str_replace($href, $new_href, $html);
            }
        }
        return $html;
    }
    
    /**
     * Disable Google Fonts nếu có local fonts tương ứng
     */
    public function maybe_disable_google_fonts() {
        $local_fonts_dir = WP_CONTENT_DIR . '/fonts';
        
        // Kiểm tra xem có local fonts không
        if (!is_dir($local_fonts_dir)) {
            return; // Không có local fonts, để Google Fonts hoạt động bình thường
        }
        
        // Lấy danh sách các font families có trong local
        $local_font_families = array();
        try {
            $subdirs = glob($local_fonts_dir . '/*', GLOB_ONLYDIR);
            if (!empty($subdirs)) {
                foreach ($subdirs as $dir) {
                    $font_family = strtolower(basename($dir));
                    $local_font_families[] = $font_family;
                }
            }
        } catch (Exception $e) {
            return; // Nếu có lỗi, để Google Fonts hoạt động bình thường
        }
        
        if (empty($local_font_families)) {
            return; // Không có local fonts
        }
        
        // Lấy fonts đang được sử dụng trong theme
        $type_headings = get_theme_mod('type_headings', array('font-family' => 'Lato'));
        $type_texts = get_theme_mod('type_texts', array('font-family' => 'Lato'));
        $type_nav = get_theme_mod('type_nav', array('font-family' => 'Lato'));
        $type_alt = get_theme_mod('type_alt', array('font-family' => 'Dancing Script'));
        
        $theme_fonts = array();
        if (is_array($type_headings) && isset($type_headings['font-family'])) {
            $theme_fonts[] = strtolower(str_replace(' ', '-', $type_headings['font-family']));
        }
        if (is_array($type_texts) && isset($type_texts['font-family'])) {
            $theme_fonts[] = strtolower(str_replace(' ', '-', $type_texts['font-family']));
        }
        if (is_array($type_nav) && isset($type_nav['font-family'])) {
            $theme_fonts[] = strtolower(str_replace(' ', '-', $type_nav['font-family']));
        }
        if (is_array($type_alt) && isset($type_alt['font-family'])) {
            $theme_fonts[] = strtolower(str_replace(' ', '-', $type_alt['font-family']));
        }
        
        $theme_fonts = array_unique($theme_fonts);
        
        // Kiểm tra xem TẤT CẢ fonts của theme có trong local không
        $all_fonts_local = true;
        foreach ($theme_fonts as $theme_font) {
            $has_local = false;
            foreach ($local_font_families as $local_font) {
                // So sánh linh hoạt (dancingscript vs dancing-script)
                $theme_font_clean = str_replace('-', '', $theme_font);
                $local_font_clean = str_replace('-', '', $local_font);
                
                if ($theme_font_clean === $local_font_clean || $theme_font === $local_font) {
                    $has_local = true;
                    break;
                }
            }
            
            if (!$has_local) {
                $all_fonts_local = false;
                break;
            }
        }
        
        // Nếu TẤT CẢ fonts đều có local, disable Google Fonts
        if ($all_fonts_local) {
            // Dequeue Google Fonts từ Flatsome
            wp_dequeue_style('flatsome-googlefonts');
            wp_deregister_style('flatsome-googlefonts');
            
            // Thêm comment để debug
            add_action('wp_head', function() {
                echo '<!-- Local fonts detected, Google Fonts disabled -->' . "\n";
            }, 999);
        }
    }
    
    /**
     * Trang settings
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>⚡ Render Blocking Optimizer</h1>
            <p>Tối ưu CSS và JavaScript để cải thiện tốc độ tải trang</p>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('rbo_settings_group');
                $options = $this->options;
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label>Defer JavaScript</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="rbo_settings[defer_js]" value="1" <?php checked($options['defer_js'], 1); ?>>
                                Thêm thuộc tính <code>defer</code> vào tất cả JavaScript
                            </label>
                            <p class="description">Giúp JavaScript không chặn quá trình render trang</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label>Async CSS</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="rbo_settings[async_css]" value="1" <?php checked($options['async_css'], 1); ?>>
                                Load CSS không đồng bộ
                            </label>
                            <p class="description">CSS sẽ load mà không chặn render</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label>Loại trừ jQuery</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="rbo_settings[exclude_jquery]" value="1" <?php checked($options['exclude_jquery'], 1); ?>>
                                Không defer jQuery (toàn site)
                            </label>
                            <p class="description">Giữ jQuery load bình thường trên toàn bộ website (tránh lỗi với một số plugin)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label>Loại trừ jQuery theo URL</label>
                        </th>
                        <td>
                            <textarea name="rbo_settings[exclude_jquery_urls]" rows="5" class="large-text code"><?php echo esc_textarea($options['exclude_jquery_urls']); ?></textarea>
                            <p class="description">
                                Không defer jQuery cho các URL cụ thể (mỗi URL một dòng)<br>
                                Hỗ trợ cả URL đầy đủ hoặc một phần của URL<br>
                                <strong>Ví dụ:</strong><br>
                                <code>https://www.filiztelek.com/widget/</code><br>
                                <code>/checkout</code><br>
                                <code>/cart</code>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label>Loại trừ Scripts</label>
                        </th>
                        <td>
                            <input type="text" name="rbo_settings[exclude_scripts]" value="<?php echo esc_attr($options['exclude_scripts']); ?>" class="regular-text">
                            <p class="description">Danh sách handle hoặc URL của scripts cần loại trừ (cách nhau bằng dấu phẩy)<br>
                            Ví dụ: <code>google-analytics, gtm</code></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label>Loại trừ Styles</label>
                        </th>
                        <td>
                            <input type="text" name="rbo_settings[exclude_styles]" value="<?php echo esc_attr($options['exclude_styles']); ?>" class="regular-text">
                            <p class="description">Danh sách handle hoặc URL của styles cần loại trừ (cách nhau bằng dấu phẩy)<br>
                            Ví dụ: <code>admin-bar, dashicons</code></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Lưu Cài Đặt'); ?>
            </form>
            
            <hr>
            
            <h2>📋 Hướng dẫn sử dụng</h2>
            <ol>
                <li>Bật các tùy chọn tối ưu phù hợp với website của bạn</li>
                <li>Test website sau khi bật để đảm bảo không có lỗi</li>
                <li>Nếu có lỗi, thêm handle của script/style vào mục loại trừ</li>
                <li>Kiểm tra lại với PageSpeed Insights</li>
            </ol>
            
            <h3>💡 Tips:</h3>
            <ul>
                <li>Nên bật "Loại trừ jQuery" nếu website dùng nhiều plugin jQuery</li>
                <li>Plugin đã tự động bảo vệ CSS quan trọng của Flatsome khỏi bị async</li>
                <li>CSS được bảo vệ: flatsome-style, flatsome-base-css, flatsome-shop, woocommerce-*</li>
                <li>Test kỹ trước khi áp dụng lên production</li>
                <li>Kết hợp với plugin cache để đạt hiệu quả tốt nhất</li>
            </ul>
            
            <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-top: 20px;">
                <strong>⚠️ Lưu ý:</strong> Sau khi thay đổi cài đặt, hãy xóa cache của website và test kỹ tất cả các chức năng.
            </div>
        </div>
        
        <style>
            .wrap h1 { font-size: 28px; margin-bottom: 15px; }
            .wrap h2 { margin-top: 30px; }
            .wrap code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
            .wrap ol li, .wrap ul li { margin-bottom: 8px; }
        </style>
        <?php
    }
}

// Khởi tạo plugin
new Render_Blocking_Optimizer();

/**
 * Hàm hỗ trợ: Thêm critical CSS inline (tùy chọn nâng cao)
 */
function rbo_add_critical_css() {
    $critical_css = get_option('rbo_critical_css', '');
    if (!empty($critical_css)) {
        echo '<style id="critical-css">' . $critical_css . '</style>';
    }
}
add_action('wp_head', 'rbo_add_critical_css', 1);

/**
 * Tối ưu Google Fonts - thay thế link cũ bằng phiên bản có display=swap
 */
function rbo_optimize_google_fonts_output() {
    ?>
    <script>
    // Tối ưu Google Fonts loading
    document.addEventListener('DOMContentLoaded', function() {
        var fontLinks = document.querySelectorAll('link[href*="fonts.googleapis.com"]');
        fontLinks.forEach(function(link) {
            if (link.href.indexOf('display=') === -1) {
                link.href = link.href + (link.href.indexOf('?') > -1 ? '&' : '?') + 'display=swap';
            }
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'rbo_optimize_google_fonts_output', 1);
?>