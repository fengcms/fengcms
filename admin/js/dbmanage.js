$(function(){
	data=$(".form").Validform({
		tiptype:2,
		callback:function(data){
			if(data.status=="y"){
				location.href ="?controller=dbmanage";
			}else{
				alert('操作失败！');
			}
		}
	});

	$("#save").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
})