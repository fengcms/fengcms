//document.writeln("<script src=\"js/tab.js\"></script>");
$(function() {
	//设置编辑器
	var K = KindEditor;
	var editor = K.editor({
			fileManagerJson : 'public/htmledit/php/file_manager_json.php',
			allowFileManager : false
		});

		//加载编辑器
		K.create('textarea[name="content"]', {
		resizeType : 1,
		newlineTag : 'p',
		allowPreviewEmoticons : false,
		allowImageUpload : false,
		afterBlur:function(){
		   this.sync();
		}
		});


		//加载模板管理
		K("#filemanager").click(function(){
			editor.loadPlugin('filemanager', function() {
				editor.plugin.filemanagerDialog({
					viewType : 'VIEW',
					dirName : '',
					clickFn : function(url, title) {
						K('#channel_template').val(url);
						editor.hideDialog();
					}
				});
			});
		});

		//加载模板管理
		K("#filemanager2").click(function(){
			editor.loadPlugin('filemanager', function() {
				editor.plugin.filemanagerDialog({
					viewType : 'VIEW',
					dirName : '',
					clickFn : function(url, title) {
						K('#classify_template').val(url);
						editor.hideDialog();
					}
				});
			});
		});
		//加载模板管理2
		K('#filemanager3').click(function() {
			editor.loadPlugin('filemanager', function() {
				editor.plugin.filemanagerDialog({
					viewType : 'VIEW',
					dirName : '',
					clickFn : function(url, title) {
						K('#content_template').val(url);
						editor.hideDialog();
					}
				});
			});
		});
		//加载模板管理2
		K('#filemanage').click(function() {
			editor.loadPlugin('filemanager', function() {
				editor.plugin.filemanagerDialog({
					viewType : 'VIEW',
					dirName : '',
					clickFn : function(url, title) {
						K('#template_url').val(url);
						editor.hideDialog();
					}
				});
			});
		});
	});



function gets(string,vals){
	var str=document.getElementById(string);
	if(string!="tags"){
		return str.value=vals.innerHTML;
	}else{
	if(str.value==""){
		return str.value+=vals.innerHTML;		
	}
		return str.value+=","+vals.innerHTML;
	}
}

