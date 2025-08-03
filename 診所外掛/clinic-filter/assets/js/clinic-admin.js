jQuery(document).ready(function($) {
    // 目標是「診所地區」這個分類的清單
    var $checklist = $('#clinic_locationchecklist');

    // 找到所有包含子項目的頂層項目（也就是縣市）
    $checklist.find('.children').each(function() {
        var $childrenList = $(this);
        var $parentLi = $childrenList.closest('li');

        // 預設先隱藏子區域
        $childrenList.hide();

        // 在縣市標籤前加上一個可以點擊的收合圖示
        var $toggle = $('<span class="toggle-children dashicons dashicons-arrow-down-alt2"></span>');
        $parentLi.find('label').first().before($toggle);

        // 幫收合圖示加上點擊事件
        $toggle.on('click', function() {
            // 切換子區域的顯示狀態（滑動效果）
            $childrenList.slideToggle('fast');
            // 切換圖示的箭頭方向
            $(this).toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-right-alt2');
        });
    });
});
