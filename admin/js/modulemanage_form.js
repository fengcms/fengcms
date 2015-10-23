$(function(){
	data=$(".form").Validform({
		tiptype:2,
		callback:function(data){
			if(data.status=="y"){
				location.href =	"?controller=modulemanage&operate=execution&type=save&re=1&id="+data.id;
			}else if(data.status=="e"){
				alert("模型名称或数据表名称已存在！")
			}else{
				location.href =	"?controller=modulemanage&operate=execution&type=save&re=0&id="+data.id;

			}
		}
	});

	$("#save").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
});