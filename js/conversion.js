;(function () {
    'use strict'

    console.log('[video_converter_fm] script loaded')

    function tnc(app, s) {
        try { if (typeof t === 'function') return t(app, s) } catch (e) {}
        return s
    }

    function closeDialog() {
        $('#linkeditor_container').remove()
        $('#linkeditor_overlay').remove()
    }

    function buildDialogHtml() {
        return '' +
            '<div id="linkeditor_overlay" class="oc-dialog-dim"></div>' +
            '<div id="linkeditor_container" class="oc-dialog" style="position: fixed; width:600px">' +
            '  <div id="linkeditor" style="padding: 16px">' +
            '    <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom:12px">' +
            '      <h3 style="margin:0">' + tnc('video_converter_fm', 'Video conversion') + '</h3>' +
            '      <button class="button" id="btnClose">' + tnc('video_converter_fm', 'Close') + '</button>' +
            '    </div>' +
            '    <div style="margin-bottom:10px">' +
            '      <label for="preset" style="display:inline-block; min-width:120px">' + tnc('video_converter_fm', 'Preset') + '</label>' +
            '      <select id="preset"><option value="fast">fast</option><option value="medium" selected>medium</option><option value="slow">slow</option></select>' +
            '    </div>' +
            '    <div style="margin-bottom:10px">' +
            '      <label for="priority" style="display:inline-block; min-width:120px">' + tnc('video_converter_fm', 'Priority') + '</label>' +
            '      <select id="priority"><option value="0" selected>0</option><option value="5">5</option><option value="10">10</option></select>' +
            '    </div>' +
            '    <div style="margin-bottom:10px">' +
            '      <label for="vcodec" style="display:inline-block; min-width:120px">' + tnc('video_converter_fm', 'Video codec') + '</label>' +
            '      <select id="vcodec"><option value="none" selected>' + tnc('video_converter_fm', 'Auto') + '</option><option value="x264">H.264</option><option value="x265">H.265</option><option value="copy">Copy</option></select>' +
            '    </div>' +
            '    <div style="margin-bottom:10px">' +
            '      <label for="vbitrate" style="display:inline-block; min-width:120px">' + tnc('video_converter_fm', 'Bitrate') + '</label>' +
            '      <select id="vbitrate"><option value="none" selected>Auto</option><option value="1">1k</option><option value="2">2k</option><option value="3">3k</option><option value="4">4k</option><option value="5">5k</option><option value="6">6k</option><option value="7">7k</option></select> <span>kbit/s</span>' +
            '    </div>' +
            '    <div style="margin-bottom:10px">' +
            '      <label for="scale" style="display:inline-block; min-width:120px">' + tnc('video_converter_fm', 'Scale to') + '</label>' +
            '      <select id="scale"><option value="none" selected>Keep</option><option value="vga">VGA</option><option value="wxga">WXGA</option><option value="hd">HD</option><option value="fhd">FHD</option><option value="uhd">4K</option></select>' +
            '    </div>' +
            '    <div style="margin-bottom:10px">' +
            '      <label for="movflags">Faststart (MP4)</label> <input type="checkbox" id="movflags" checked>' +
            '    </div>' +
            '    <p style="margin: 10px 0">' + tnc('video_converter_fm', 'Choose output format:') + '</p>' +
            '    <div id="buttons"><a class="button primary" id="mp4">.MP4</a><a class="button primary" id="avi">.AVI</a><a class="button primary" id="m4v">.M4V</a><a class="button primary" id="webm">.WEBM</a><a class="button primary" id="mpd">.DASH</a></div>' +
            '  </div></div>'
    }

    function showConversionDialog(filename, context) {
        var preset='medium',priority='0',vcodec='none',vbitrate='none',scaling='none',faststart=true
        $('body').append(buildDialogHtml())
        $('#btnClose').on('click', closeDialog)
        $('#linkeditor_overlay').on('click', closeDialog)
        $('#preset').on('change', function(e){preset=e.target.value})
        $('#priority').on('change', function(e){priority=e.target.value})
        $('#vcodec').on('change', function(e){vcodec=e.target.value})
        $('#vbitrate').on('change', function(e){vbitrate=e.target.value})
        $('#scale').on('change', function(e){scaling=e.target.value})
        $('#movflags').on('change', function(e){faststart=!!e.target.checked})
        try{var fileExt=(filename.split('.').pop()||'').toLowerCase();['avi','mp4','m4v','webm','mpd'].forEach(function(type){if(type===fileExt)$('#'+type).css({backgroundColor:'lightgray',borderColor:'lightgray',pointerEvents:'none'})})}catch(e){}
        function onChooseFormat(evt){
            var format=evt.target.id
            var external=(context&&context.fileInfoModel&&context.fileInfoModel.attributes&&context.fileInfoModel.attributes.mountType==='external')?1:0
            var data={nameOfFile:filename,directory:context&&context.dir?context.dir:'/',external:external,type:format,preset:preset,priority:priority,movflags:faststart,codec:(vcodec==='none'?null:vcodec),vbitrate:(vbitrate==='none'?null:vbitrate),scale:(scaling==='none'?null:scaling)}
            try{if(context&&context.fileInfoModel&&context.fileInfoModel.attributes&&context.fileInfoModel.attributes.mtime)data.mtime=context.fileInfoModel.attributes.mtime}catch(e){}
            try{if(external===0&&context&&context.fileList&&context.fileList.dirInfo&&context.fileList.dirInfo.shareOwnerId)data.shareOwner=context.fileList.dirInfo.shareOwnerId}catch(e){}
            var tr=context&&context.fileList?context.fileList.findFileEl(filename):null
            if(context&&context.fileList&&tr)context.fileList.showFileBusyState(tr,true)
            $('#buttons a.button').attr('disabled','disabled')
              $.ajax({type:'POST',async:true,url:OC.filePath('video_converter_fm','ajax','convertHere.php'),data:data,dataType:'json',success:function(resp){try{if(typeof resp==='string'){resp=JSON.parse(resp)};if(resp&&resp.code===1){console.log('[video_converter_fm] conversion success',resp)}else{console.error('[video_converter_fm] conversion returned error',resp)}}catch(e){console.error('[video_converter_fm] conversion parse error',e,resp)}},error:function(xhr){console.error('[video_converter_fm] conversion failed',xhr.status,xhr.statusText,xhr.responseText)},complete:function(){if(context&&context.fileList&&tr)context.fileList.showFileBusyState(tr,false);closeDialog()}})
        }
        $('#buttons').find('a.button').on('click',onChooseFormat)
    }

    function registerNC32Action(){
    if(!window._nc_fileactions){console.log('[video_converter_fm] _nc_fileactions not available yet');return false}
        try{
            var actionDef={id:'video-convert',displayName:function(nodes){return tnc('video_converter_fm','Convert into')},iconSvgInline:function(){return '<svg width="16" height="16" viewBox="0 0 16 16"><path d="M8 2a6 6 0 1 0 0 12A6 6 0 0 0 8 2zm0 1a5 5 0 1 1 0 10A5 5 0 0 1 8 3zm-.5 2v3.5H5l3 3 3-3H8.5V5h-1z"/></svg>'},enabled:function(nodes){if(nodes.length!==1)return false;var node=nodes[0];if(!node||!node.mime)return false;return node.mime.startsWith('video/')},exec:function(node){var filename=node.basename;var context={dir:node.dirname||'/',fileInfoModel:{attributes:{mountType:node.attributes&&node.attributes.mountType,mtime:node.mtime}},fileList:{dirInfo:{shareOwnerId:node.attributes&&node.attributes['owner-id']},findFileEl:function(){return null},showFileBusyState:function(){}}};showConversionDialog(filename,context)},order:50}
            if(typeof window._nc_fileactions==='function'){window._nc_fileactions(actionDef)}else if(window._nc_fileactions&&typeof window._nc_fileactions.push==='function'){window._nc_fileactions.push(actionDef)}else if(window._nc_fileactions&&typeof window._nc_fileactions.registerAction==='function'){window._nc_fileactions.registerAction(actionDef)}else{console.warn('[video_converter_fm] unknown _nc_fileactions structure',typeof window._nc_fileactions);return false}
            console.log('[video_converter_fm] NC32 file action registered via _nc_fileactions');return true
        }catch(e){console.error('[video_converter_fm] failed to register NC32 action',e);return false}
    }

    function tryRegister(){if(!registerNC32Action()){setTimeout(tryRegister,500)}}

    if(document.readyState==='complete'||document.readyState==='interactive'){console.log('[video_converter_fm] attempting registration (dom ready)');tryRegister()}else{document.addEventListener('DOMContentLoaded',function(){console.log('[video_converter_fm] attempting registration (DOMContentLoaded)');tryRegister()})}
})()
