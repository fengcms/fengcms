$(function(){
	data=$(".form").Validform({
		callback:function(data){
			if(data.status=="y"){
				location.href =	"?controller=signup&operate=execution&type=exc&re=1";
			}else if(data.status=="a"){
				alert('请选择栏目！');
			}else if(data.status=="n"){
				location.href =	"?controller=signup&operate=execution&type=exc&re=0";
			}
		}
	});
	$("#delete").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
});