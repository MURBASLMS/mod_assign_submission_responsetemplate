define(['core/log'], function(Log) {
    return {
        init: function(data) {
            Log.debug('Response Template: Sidebar loading...');
            
            var count = 0;
            var interval = setInterval(function() {
                count++;
                var editor = window.tinymce ? window.tinymce.get('id_onlinetext_editor') : null;

                if (editor && editor.initialized) {
                    clearInterval(interval);

                    setTimeout(function() {
                        // 1. Force Fullscreen if not already.
                        if (editor.plugins.fullscreen && !document.body.classList.contains('tox-fullscreen')) {
                            editor.execCommand('mceFullScreen');
                        }

                        // 2. Build the Sidebar DOM.
                        var wrap = document.querySelector('.tox-sidebar-wrap');
                        if (wrap && !wrap.querySelector('.rt-sidebar')) {
                            
                            var html = '<div class="rt-sidebar">';
                            html += '<div class="rt-sidebar-title">Assignment Reference</div>';
                            html += '<div class="rt-sidebar-content">';
                            
                            // Description Section.
                            if (data.description && data.description.trim() !== "") {
                                html += '<div class="panel-section">' +
                                        '<span class="panel-header">Description</span>' +
                                        '<div class="panel-body">' + data.description + '</div></div>';
                            }
                            
                            // Instructions Section (With extra check for Moodle empty tags).
                            var hasInstructions = data.instructions && 
                                                 data.instructions.trim() !== "" && 
                                                 data.instructions !== "<p></p>";

                            if (hasInstructions) {
                                html += '<div class="panel-section">' +
                                        '<span class="panel-header">Instructions</span>' +
                                        '<div class="panel-body">' + data.instructions + '</div></div>';
                            }

                            if (!data.description && !hasInstructions) {
                                html += '<div class="panel-section">No reference content available.</div>';
                            }

                            html += '</div></div>';
                            wrap.insertAdjacentHTML('beforeend', html);
                            Log.debug('Response Template: Sidebar Injected');
                        }
                    }, 800);
                }
                if (count > 50) clearInterval(interval);
            }, 200);
        }
    };
});