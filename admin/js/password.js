$(function(){
	data=$(".form").Validform({
		tiptype:2,
		callback:function(data){
			if(data.status=="y"){
				location.href ="?controller=manage&operate=password";
			}else{
				alert('保存失败！');
			}
		}
	});

	$("#save").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
})