<?php
/*
Plugin Name: Clinic Filter
Version: 1.0.1
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

// 1. 註冊「診所」Post Type
if (!function_exists('clinic_filter_register_post_type')) {
    function clinic_filter_register_post_type() {
        register_post_type('clinic', array(
            'labels' => array(
                'name'               => '診所',
                'singular_name'      => '診所',
                'menu_name'          => '診所管理',
                'add_new'            => '新增診所',
                'add_new_item'       => '新增診所',
                'edit_item'          => '編輯診所',
                'new_item'           => '新診所',
                'view_item'          => '查看診所',
                'search_items'       => '搜尋診所',
                'not_found'          => '找不到診所',
                'not_found_in_trash' => '回收桶中沒有診所'
            ),
            'public'      => true,
            'has_archive' => true,
            'rewrite'     => array('slug' => 'clinic'),
            'supports'    => array('title','thumbnail'),
        ));

        // 註冊 ACF 欄位
        if (function_exists('acf_add_local_field_group')) {
            acf_add_local_field_group(array(
                'key' => 'group_clinic_details',
                'title' => '診所詳細資料',
                'fields' => array(
                    array(
                        'key' => 'field_address',
                        'label' => '地址',
                        'name' => 'address',
                        'type' => 'text',
                        'instructions' => '請輸入診所完整地址',
                        'required' => 1,
                    ),
                    array(
                        'key' => 'field_phone',
                        'label' => '電話',
                        'name' => 'phone',
                        'type' => 'text',
                        'instructions' => '請輸入診所電話',
                        'required' => 0,
                    ),
                    array(
                        'key' => 'field_store_website',
                        'label' => '店家網址',
                        'name' => 'store_website',
                        'type' => 'url',
                        'instructions' => '請輸入完整的網址（包含 http:// 或 https://）',
                        'required' => 0,
                    ),
                ),
                'location' => array(
                    array(
                        array(
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'clinic',
                        ),
                    ),
                ),
                'menu_order' => 0,
                'position' => 'acf_after_title',  // 將自訂欄位置於標題下方
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
            ));
        }
    }
    add_action('init', 'clinic_filter_register_post_type');
}

// 2. 註冊「診所地區」taxonomy
function clinic_filter_register_taxonomy_init() {
    $labels = array(
        'name'              => '地區',
        'singular_name'     => '地區',
        'search_items'      => '搜尋地區',
        'all_items'         => '所有地區',
        'parent_item'       => '上層地區',
        'parent_item_colon' => '上層地區:',
        'edit_item'         => '編輯地區',
        'update_item'       => '更新地區',
        'add_new_item'      => '新增地區',
        'new_item_name'     => '新地區名稱',
        'menu_name'         => '地區',
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'clinic-location'),
    );

    register_taxonomy('clinic_location', array('clinic'), $args);
}
add_action('init', 'clinic_filter_register_taxonomy_init', 0);

// 3. 隱藏預設編輯器
add_action('admin_init', function() {
    remove_post_type_support('clinic', 'editor');
    remove_post_type_support('clinic', 'excerpt');
    remove_post_type_support('clinic', 'comments');
    remove_post_type_support('clinic', 'trackbacks');
    remove_post_type_support('clinic', 'custom-fields');
    remove_post_type_support('clinic', 'revisions');
    remove_post_type_support('clinic', 'page-attributes');
    remove_post_type_support('clinic', 'post-formats');
});

// 4. 前端樣式 & JS
if (!function_exists('clinic_filter_enqueue_scripts')) {
    function clinic_filter_enqueue_scripts() {
        // 載入 jQuery
        wp_enqueue_script('jquery');

        // 前端樣式
        wp_enqueue_style(
            'clinic-filter-style',
            plugins_url('assets/css/clinic-filter.css', __FILE__),
            array(),
            '1.0.0'
        );

        // 前端腳本
        wp_enqueue_script(
            'clinic-filter-script',
            plugins_url('assets/js/clinic-filter.js', __FILE__),
            array('jquery'),
            '1.0.0',
            true
        );

        // 本地化腳本
        wp_localize_script('clinic-filter-script', 'clinicAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('clinic_filter_nonce')
        ));
    }
    add_action('wp_enqueue_scripts', 'clinic_filter_enqueue_scripts');
}

// 4. 短代碼：搜尋表單
if (!function_exists('clinic_search_bar_shortcode')) {
    function clinic_search_bar_shortcode($atts) {
        $atts = shortcode_atts(array(), $atts, 'clinic_search_bar');
        
        ob_start();
        ?>
        <div class="clinic-filter-container">
            <form id="clinic-filter-form" class="row g-3 align-items-center">
                <div class="col-12 col-md-3">
                    <button type="button" id="reset-filters" class="btn btn-primary w-100">
                        <i class="fas fa-undo"></i> 全部診所
                    </button>
                </div>
                <div class="col-12 col-md-3">
                    <input type="text" class="form-control" id="clinic-keyword" placeholder="搜尋診所...">
                </div>
                <div class="col-12 col-md-3">
                    <?php
                    // 獲取所有頂層分類（地區）
                    $regions = get_terms(array(
                        'taxonomy'   => 'clinic_location',
                        'hide_empty' => false,
                        'parent'     => 0,
                        'orderby'    => 'name',
                        'order'      => 'ASC'
                    ));
                    ?>
                    <select class="form-select" id="clinic-region">
                        <option value="">選擇地區</option>
                        <?php foreach ($regions as $region) : ?>
                            <option value="<?php echo esc_attr($region->term_id); ?>">
                                <?php echo esc_html($region->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <?php
                    // 1. 先獲取所有頂層分類
                    $parent_terms = get_terms(array(
                        'taxonomy' => 'clinic_location',
                        'parent' => 0,
                        'hide_empty' => false
                    ));

                    // 2. 收集所有子分類 ID
                    $child_ids = [];
                    if (!is_wp_error($parent_terms) && !empty($parent_terms)) {
                        foreach ($parent_terms as $parent) {
                            $children = get_terms(array(
                                'taxonomy' => 'clinic_location',
                                'parent' => $parent->term_id,
                                'hide_empty' => false
                            ));
                            
                            if (!is_wp_error($children) && !empty($children)) {
                                foreach ($children as $child) {
                                    $child_ids[] = $child->term_id;
                                }
                            }
                        }
                    }

                    // 3. 獲取所有子分類
                    $cities = [];
                    if (!empty($child_ids)) {
                        $cities = get_terms(array(
                            'taxonomy' => 'clinic_location',
                            'include' => $child_ids,
                            'orderby' => 'name',
                            'order' => 'ASC',
                            'hide_empty' => false
                        ));
                    }
                    ?>
                    <select class="form-select" id="clinic-city">
                        <option value="">選擇縣市</option>
                        <?php if (!is_wp_error($cities) && !empty($cities)) : ?>
                            <?php foreach ($cities as $city) : ?>
                                <option value="<?php echo esc_attr($city->term_id); ?>">
                                    <?php echo esc_html($city->name); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    add_shortcode('clinic_search_bar', 'clinic_search_bar_shortcode');
}

// 5. 短代碼：診所列表
if (!function_exists('clinic_list_shortcode')) {
    function clinic_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'posts_per_page' => 25,
        ), $atts, 'clinic_list');
        
        ob_start();
        ?>
        <div id="clinic-list-container">
            <?php 
            $initial_results = clinic_filter_generate_list(0, 0, '', 0);
            if (is_array($initial_results)) {
                echo $initial_results['data'];
            } else {
                echo $initial_results; // This handles the case where a string is returned directly
            }
            ?>
        </div>
        <div class="pagination-container mt-4">
            <?php echo is_array($initial_results) ? $initial_results['pagination'] : ''; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    add_shortcode('clinic_list', 'clinic_list_shortcode');
}

// 6. 產生列表 HTML
if (!function_exists('clinic_filter_generate_list')) {
    function clinic_filter_generate_list($city_id = 0, $area_id = 0, $keyword = '', $paged = 1) {
        // 參數驗證
        $city_id = intval($city_id);
        $area_id = intval($area_id);
        $keyword = sanitize_text_field($keyword);
        $paged = max(1, intval($paged));

        // 準備查詢參數
        $args = array(
            'post_type'      => 'clinic',
            'posts_per_page' => 25,
            'paged'          => $paged,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        );

        // **修正後的篩選邏輯**
        $tax_query = [];
        // 優先使用最精確的篩選條件：縣市 (city_id)
        if ($city_id > 0) {
            $tax_query[] = array(
                'taxonomy' => 'clinic_location',
                'field'    => 'term_id',
                'terms'    => $city_id,
                'include_children' => false // 縣市是最底層，不需包含子項
            );
        } 
        // 如果沒有選縣市，但選了地區 (area_id)，則查詢該地區下的所有診所
        elseif ($area_id > 0) {
            $tax_query[] = array(
                'taxonomy' => 'clinic_location',
                'field'    => 'term_id',
                'terms'    => $area_id,
                'include_children' => true // 地區是父層，需要包含所有子縣市
            );
        }

        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        // 關鍵字搜尋
        if (!empty($keyword)) {
            $args['s'] = $keyword;
        }

        $query = new WP_Query($args);

        ob_start();

        if ($query->have_posts()) {
            echo '<div class="clinic-list">';
            while ($query->have_posts()) {
                $query->the_post();

                $address = get_field('address', get_the_ID());
                $phone = get_field('phone', get_the_ID());
                $store_website = get_field('store_website', get_the_ID());

                $category_name = '';
                $locations = get_the_terms(get_the_ID(), 'clinic_location');
                if ($locations && !is_wp_error($locations)) {
                    foreach ($locations as $location) {
                        if ($location->parent != 0) {
                            $category_name = $location->name;
                            break; 
                        }
                    }
                }
                ?>
                <a href="<?php echo $store_website ? esc_url($store_website) : '#'; ?>" class="clinic-item-link" <?php echo $store_website ? 'target="_blank"' : ''; ?>>
                    <div class="clinic-item">
                        <div class="clinic-left">
                            <div class="clinic-name">
                                <?php if ($category_name) : ?>
                                    <span class="s-item-city"><?php echo esc_html($category_name); ?></span>
                                <?php endif; ?>
                                <?php the_title(); ?>
                            </div>
                        </div>
                        <div class="clinic-right">
                            <div class="clinic-info">
                                <?php if ($address) : ?>
                                    <div class="clinic-address"><?php echo esc_html($address); ?></div>
                                <?php endif; ?>
                                <?php if ($phone) : ?>
                                    <div class="clinic-phone"><?php echo esc_html($phone); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </a>
                <?php
            }
            echo '</div>';
        } else {
            echo '<div class="alert alert-info">' . (!empty($keyword) ? '搜尋不到此診所' : '此地區暫無認證診所') . '</div>';
        }

        $list_html = ob_get_clean();
        wp_reset_postdata();

        // 生成分頁 HTML
        $pagination_html = '';
        $total_pages = $query->max_num_pages;
        if ($total_pages > 1) {
            $current_page = $paged;
            $pagination_html = '<div class="clinic-pagination">';
            if ($current_page > 1) {
                $pagination_html .= '<a href="#" class="page-numbers prev" data-page="' . ($current_page - 1) . '">&laquo; 上一頁</a>';
            }
            for ($i = 1; $i <= $total_pages; $i++) {
                if ($i == $current_page) {
                    $pagination_html .= '<span class="page-numbers current">' . $i . '</span>';
                } else {
                    $pagination_html .= '<a href="#" class="page-numbers" data-page="' . $i . '">' . $i . '</a>';
                }
            }
            if ($current_page < $total_pages) {
                $pagination_html .= '<a href="#" class="page-numbers next" data-page="' . ($current_page + 1) . '">下一頁 &raquo;</a>';
            }
            $pagination_html .= '</div>';
        }

        $response = array(
            'success'      => $query->have_posts(),
            'data'         => $list_html,
            'pagination'   => $pagination_html,
            'current_page' => $paged,
            'max_pages'    => $total_pages
        );

        return $response;
    }
}

if (!function_exists('clinic_filter_get_districts_ajax')) {
    function clinic_filter_get_districts_ajax() {
        // 驗證 nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'clinic_filter_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // 獲取參數
        $region_id = isset($_POST['city_id']) ? sanitize_text_field($_POST['city_id']) : '';
        
        // 準備查詢參數
        $args = array(
            'taxonomy'   => 'clinic_location',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC'
        );
        
        if ($region_id === 'all') {
            // 1. 先獲取所有頂層分類
            $parent_terms = get_terms(array(
                'taxonomy' => 'clinic_location',
                'parent' => 0,
                'hide_empty' => false
            ));

            // 2. 收集所有子分類 ID
            $child_ids = [];
            if (!is_wp_error($parent_terms) && !empty($parent_terms)) {
                foreach ($parent_terms as $parent) {
                    $children = get_terms(array(
                        'taxonomy' => 'clinic_location',
                        'parent' => $parent->term_id,
                        'hide_empty' => false
                    ));
                    
                    if (!is_wp_error($children) && !empty($children)) {
                        foreach ($children as $child) {
                            $child_ids[] = $child->term_id;
                        }
                    }
                }
            }

            // 3. 獲取所有子分類
            if (!empty($child_ids)) {
                $args['include'] = $child_ids;
            } else {
                wp_send_json_success('');
                return;
            }
        } else if (is_numeric($region_id) && $region_id > 0) {
            // 獲取特定地區下的縣市
            $args['parent'] = intval($region_id);
        } else {
            // 如果沒有提供有效的 region_id，返回空結果
            wp_send_json_success('');
            return;
        }
        
        // 獲取縣市列表
        $cities = get_terms($args);
        
        // 生成選項 HTML
        $options = '';
        if (!is_wp_error($cities) && !empty($cities)) {
            foreach ($cities as $city) {
                $options .= sprintf(
                    '<option value="%s">%s</option>',
                    esc_attr($city->term_id),
                    esc_html($city->name)
                );
            }
        }
        
        // 返回 JSON 響應
        wp_send_json_success($options);
    }
    add_action('wp_ajax_clinic_filter_get_districts', 'clinic_filter_get_districts_ajax');
    add_action('wp_ajax_nopriv_clinic_filter_get_districts', 'clinic_filter_get_districts_ajax');
}

// 8. AJAX：篩選
if (!function_exists('clinic_filter_ajax_search')) {
    function clinic_filter_ajax_search() {
        check_ajax_referer('clinic_filter_nonce', 'nonce');
        
        // 獲取並清理參數
        $city_id  = isset($_POST['city_id']) ? sanitize_text_field($_POST['city_id']) : '';
        $area_id  = isset($_POST['area_id']) ? sanitize_text_field($_POST['area_id']) : '';
        $keyword  = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
        $paged    = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : 1;
        
        // 轉換為數字ID（如果非空）
        $city_id = !empty($city_id) ? intval($city_id) : 0;
        $area_id = !empty($area_id) ? intval($area_id) : 0;
        
        // 調用生成列表函數
        $result = clinic_filter_generate_list($city_id, $area_id, $keyword, ($paged - 1) * 25);
        
        // 檢查結果
        if (is_array($result) && isset($result['success']) && $result['success']) {
            $response = array(
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination'],
                'current_page' => $paged,
                'max_pages' => $result['max_pages']
            );
            wp_send_json_success($response);
        } else {
            // 如果沒有結果，返回空結果
            $response = array(
                'success' => false,
                'data' => '<div class="alert alert-info">沒有找到符合條件的診所</div>',
                'pagination' => '',
                'current_page' => 1,
                'max_pages' => 1
            );
            wp_send_json_success($response);
        }
    }
    add_action('wp_ajax_clinic_filter', 'clinic_filter_ajax_search');
    add_action('wp_ajax_nopriv_clinic_filter', 'clinic_filter_ajax_search');
}

// 9. 後台：載入後台專用 JS & CSS
if (!function_exists('clinic_filter_admin_enqueue_scripts')) {
    function clinic_filter_admin_enqueue_scripts($hook) {
        // 只在編輯頁面載入
        if ($hook == 'post.php' || $hook == 'post-new.php') {
            global $post;
            if ($post && $post->post_type === 'clinic') {
                // 載入 Dashicons (收合圖示需要)
                wp_enqueue_style('dashicons');

                // 載入後台 CSS
                wp_enqueue_style(
                    'clinic-admin-style',
                    plugins_url('assets/css/clinic-admin.css', __FILE__),
                    array(),
                    '1.0.0'
                );

                // 載入後台 JS
                wp_enqueue_script(
                    'clinic-admin-script',
                    plugins_url('assets/js/clinic-admin.js', __FILE__),
                    array('jquery'),
                    '1.0.0',
                    true
                );
            }
        }
    }
    add_action('admin_enqueue_scripts', 'clinic_filter_admin_enqueue_scripts');
}
