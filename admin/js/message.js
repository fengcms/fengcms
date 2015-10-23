$(function(){
	data=$(".form").Validform({
		callback:function(data){
			if(data.status=="y"){
				alert('记录删除成功！');
				location.href =	"?controller=message";
			}else if(data.status=="e"){
				alert('请选择留言记录！');
			}else if(data.status=="n"){
				alert('记录删除失败！');
				location.href =	"?controller=message";
			}
		}
	});
	$("#delete").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
});