$(document).ready(function() {
	data=$(".form").Validform({
		tiptype:2,
		callback:function(data){
			if(data.status=="y"){
				location.href ="?controller=signup&operate=execution&type=save&re=1&id="+data.id;
			}else{
				location.href ="?controller=signup&operate=execution&type=save&re=0&id="+data.id;
			}
		}
	});

	$("#save").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
});