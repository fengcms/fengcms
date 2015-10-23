$(function(){
	data=$(".form").Validform({
		tiptype:2,
		callback:function(data){
			if(data.status=="y"){
				location.href =	"?controller=base&operate=execution&url="+encodeURIComponent('?controller=base')+"&re=1";
			}else{
				location.href =	"?controller=base&operate=execution&url="+encodeURIComponent('?controller=base')+"&re=0";

			}
		}
	});

	$("#save").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
});