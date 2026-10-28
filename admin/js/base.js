$(function(){
	data=$(".form").Validform({
		tiptype:2,
		callback:function(data){
			var result=data && data.responseJSON ? data.responseJSON : data;
			if(result.status=="y"){
				location.href =	"?controller=base&operate=execution&url="+encodeURIComponent('?controller=base')+"&re=1";
			}else{
				alert(result.message || "保存失败，请刷新页面后重试。");
			}
		}
	});

	$("#save").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
});
