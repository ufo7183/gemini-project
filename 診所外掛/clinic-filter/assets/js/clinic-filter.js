jQuery(document).ready(function($) {
    var isLoading = false;
    var searchTimer = null;
    var currentPage = 1;

    // 載入指定地區的縣市
    function loadCitiesByRegion(regionId = '') {
        const $citySelect = $("#clinic-city");
        const defaultOption = '<option value="">選擇縣市</option>';
        
        $citySelect.html(defaultOption).prop('disabled', true);
        
        if (!regionId) {
            // 如果沒有選擇地區，載入所有縣市
            loadAllCities();
            return;
        }
        
        $.ajax({
            url: clinicAjax.ajaxurl,
            method: "POST",
            data: {
                action: 'clinic_filter_get_districts',
                city_id: regionId,
                nonce: clinicAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $citySelect.html(defaultOption + response.data);
                } else {
                    console.error('獲取縣市資料失敗');
                    $citySelect.html(defaultOption + '<option value="">載入失敗</option>');
                }
            },
            error: function() {
                console.error('AJAX 請求失敗');
                $citySelect.html(defaultOption + '<option value="">載入失敗</option>');
            },
            complete: function() {
                $citySelect.prop('disabled', false);
            }
        });
    }
    
    // 載入所有縣市
    function loadAllCities() {
        const $citySelect = $("#clinic-city");
        const defaultOption = '<option value="">選擇縣市</option>';
        
        $citySelect.html(defaultOption).prop('disabled', true);
        
        $.ajax({
            url: clinicAjax.ajaxurl,
            method: "POST",
            data: {
                action: 'clinic_filter_get_districts',
                city_id: 'all',
                nonce: clinicAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $citySelect.html(defaultOption + response.data);
                } else {
                    console.error('獲取縣市資料失敗');
                    $citySelect.html(defaultOption + '<option value="">載入失敗</option>');
                }
            },
            error: function() {
                console.error('AJAX 請求失敗');
                $citySelect.html(defaultOption + '<option value="">載入失敗</option>');
            },
            complete: function() {
                $citySelect.prop('disabled', false);
            }
        });
    }

    // 篩選診所函數
    function filterClinics(city_id, area_id, keyword, page = 1, isInitialLoad = false) {
        if (isLoading) return;
        isLoading = true;
        
        // 確保參數正確
        city_id = city_id || '';
        area_id = area_id || '';
        keyword = keyword || '';
        
        // 如果不是初始載入，顯示載入動畫
        if (!isInitialLoad) {
            $("#clinic-list-container").html('<div class="text-center my-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">載入中...</span></div></div>');
        }
        
        $.ajax({
            url: clinicAjax.ajaxurl,
            method: "POST",
            data: {
                action: 'clinic_filter',
                city_id: city_id,
                area_id: area_id,
                keyword: keyword,
                paged: page,
                nonce: clinicAjax.nonce
            },
            success: function(response) {
                console.log('AJAX Response:', response); // 調試用
                
                if (response.success && response.data) {
                    // 更新列表內容
                    $("#clinic-list-container").html(response.data.data || response.data);
                    
                    // 更新分頁
                    if (response.data.pagination) {
                        $(".pagination-container").html(response.data.pagination);
                    } else if (response.pagination) {
                        $(".pagination-container").html(response.pagination);
                    } else {
                        $(".pagination-container").empty();
                    }
                    
                    // 更新當前頁面
                    currentPage = response.data.current_page || parseInt(page);
                    
                    // 滾動到列表頂部
                    $('html, body').animate({
                        scrollTop: $("#clinic-list-container").offset().top - 100
                    }, 500);
                } else {
                    console.error('伺服器回應錯誤或無數據:', response);
                    $("#clinic-list-container").html('<div class="alert alert-info">' + (response.data || '搜尋不到此診所') + '</div>');
                    $(".pagination-container").empty();
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", status, error);
                $("#clinic-list-container").html('<div class="alert alert-danger">載入失敗，請稍後再試</div>');
                $(".pagination-container").empty();
            },
            complete: function() {
                isLoading = false;
            }
        });
    }

    // 分頁點擊事件
    $(document).on("click", ".page-numbers:not(.current)", function(e) {
        e.preventDefault();
        if (isLoading) return;
        
        const page = $(this).data('page');
        if (!page) return;
        
        const regionId = $("#clinic-region").val() || '';
        const cityId = $("#clinic-city").val() || '';
        const keyword = $("#clinic-keyword").val().trim();
        
        filterClinics(cityId, regionId, keyword, page);
    });

    // 全部診所按鈕點擊事件
    $(document).on("click", "#reset-filters", function(e) {
        e.preventDefault();
        $("#clinic-region, #clinic-city").val('');
        $("#clinic-keyword").val('');
        loadAllCities();
        // 重新載入頁面以顯示所有診所
        window.location.href = window.location.pathname;
    });

    // 地區變更事件
    $(document).on("change", "#clinic-region", function() {
        const regionId = $(this).val() || '';
        const keyword = $("#clinic-keyword").val().trim();
        
        // 載入該地區的縣市
        loadCitiesByRegion(regionId);
        
        // 觸發篩選（重置到第一頁）
        filterClinics('', regionId, keyword, 1);
    });
    
    // 縣市變更時觸發搜尋
    $(document).on("change", "#clinic-city", function() {
        const regionId = $("#clinic-region").val() || '';
        const cityId = $(this).val() || '';
        const keyword = $("#clinic-keyword").val().trim();
        
        console.log('City changed:', {cityId, regionId, keyword}); // 調試用
        filterClinics(cityId, regionId, keyword, 1);
    });
    
    // 即時搜尋功能
    $("#clinic-keyword").on("input", function() {
        // 清除之前的計時器
        if (searchTimer) {
            clearTimeout(searchTimer);
        }
        
        // 設置新的計時器，延遲 300 毫秒後執行搜尋
        searchTimer = setTimeout(function() {
            const regionId = $("#clinic-region").val() || '';
            const cityId = $("#clinic-city").val() || '';
            const keyword = $("#clinic-keyword").val().trim();
            filterClinics(cityId, regionId, keyword, 1);
        }, 300);
    });
    
    // 初始載入時只載入城市選單，不觸發篩選
    loadAllCities();
    
    // 移除自動觸發篩選的代碼
    // 初始內容已經由 PHP 生成，不需要再通過 AJAX 載入
});
