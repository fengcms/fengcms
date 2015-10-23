$(function(){
	data=$(".form").Validform({
		tiptype:2,
		callback:function(data){
			if(data.status=="y"){
				location.href =	"?controller=fieldmanage&operate=execution&type=save&re=1&id="+data.id;
			}else if(data.status=="e"){
				alert("您没有选择字段！")
			}else{
				location.href =	"?controller=fieldmanage&operate=execution&type=save&re=0&id="+data.id;

			}
		}
	});

	$("#save").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
});