$(function(){
	data=$(".form").Validform({
		tiptype:2,
		callback:function(data){
			if(data.status=="y"){
				alert('操作成功！');
				location.href ="?controller=classify&operate=sorts";
			}else if(data.status=="a"){
				alert('请选择记录！');
			}else if(data.status=="n"){
				alert('操作失败');
			}
		}
	});

	$("#save").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
});