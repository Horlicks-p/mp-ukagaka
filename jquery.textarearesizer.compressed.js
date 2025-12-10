(function($) {
    // 定義全局變數
    var textarea,
    staticOffset; // textarea: 當前操作的文本框, staticOffset: 靜態偏移量
    var iLastMousePos = 0; // 上一次滑鼠的 Y 位置
    var iMin = 32; // textarea 的最小高度（像素）
    var grip; // 拖曳柄（未使用，但保留）

    // TextAreaResizer 插件主函數
    $.fn.TextAreaResizer = function() {
        return this.each(function() {
            // 為每個 textarea 初始化
            textarea = $(this).addClass(
            'processed'); // 標記為已處理
            staticOffset = null;

            // 包裹 textarea 並添加拖曳柄
            $(this)
                .wrap(
                    '<div class="resizable-textarea"><span></span></div>'
                    ) // 包裹在一個 div 中
                .parent()
                .append(
                    $('<div class="grippie"></div>')
                    .bind("mousedown", {
                        el: this
                    },
                    startDrag) // 添加拖曳柄並綁定 mousedown 事件
                );

            // 調整拖曳柄的右邊距，使其與 textarea 對齊
            var grippie = $('div.grippie', $(this)
                .parent())[0];
            grippie.style.marginRight = (grippie
                .offsetWidth - $(this)[0]
                .offsetWidth) + 'px';
        });
    };

    // 開始拖曳的處理函數
    function startDrag(e) {
        textarea = $(e.data.el); // 獲取當前 textarea
        textarea.blur(); // 讓 textarea 失去焦點
        iLastMousePos = mousePosition(e).y; // 記錄滑鼠初始 Y 位置
        staticOffset = textarea.height() -
        iLastMousePos; // 計算靜態偏移量
        textarea.css('opacity', 0.25); // 設置透明度，表示拖曳中
        $(document)
            .mousemove(performDrag) // 綁定拖曳移動事件
            .mouseup(endDrag); // 綁定拖曳結束事件
        return false; // 阻止預設行為
    }

    // 執行拖曳的處理函數
    function performDrag(e) {
        var iThisMousePos = mousePosition(e).y; // 當前滑鼠 Y 位置
        var iMousePos = staticOffset + iThisMousePos; // 計算新的高度
        if(iLastMousePos >= iThisMousePos) {
            iMousePos -= 5; // 若滑鼠向上移動，略微減小高度
        }
        iLastMousePos = iThisMousePos; // 更新上一次滑鼠位置
        iMousePos = Math.max(iMin, iMousePos); // 確保高度不小於最小值
        textarea.height(iMousePos + 'px'); // 設置 textarea 高度
        if(iMousePos < iMin) {
            endDrag(e); // 若低於最小高度，結束拖曳
        }
        return false; // 阻止預設行為
    }

    // 結束拖曳的處理函數
    function endDrag(e) {
        $(document)
            .unbind('mousemove', performDrag) // 解綁拖曳移動事件
            .unbind('mouseup', endDrag); // 解綁拖曳結束事件
        textarea.css('opacity', 1); // 恢復透明度
        textarea.focus(); // 讓 textarea 重新獲得焦點
        textarea = null; // 清空全局變數
        staticOffset = null;
        iLastMousePos = 0;
    }

    // 獲取滑鼠位置的輔助函數
    function mousePosition(e) {
        return {
            x: e.clientX + document.documentElement
            .scrollLeft, // X 座標（考慮滾動偏移）
            y: e.clientY + document.documentElement
                .scrollTop // Y 座標（考慮滾動偏移）
        };
    }
})(jQuery);