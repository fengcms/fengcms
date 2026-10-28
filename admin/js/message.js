$(function(){
	data=$(".form").Validform({
		callback:function(data){
			if(data.status=="y"){
				alert(FengAdmin.batchSummary(data) || '记录删除成功！');
				location.href =	"?controller=message";
			}else if(data.status=="e"){
				alert('请选择留言记录！');
			}else if(data.status==="p"){
				alert((FengAdmin.batchSummary(data) || '部分留言未完成。') + '\n保留当前选择，可再次提交。');
			}else{
				alert((data.responseJSON && data.responseJSON.message) || data.message || '删除失败，请重试。');
			}
		}
	});
	$("#delete").click(function(){
		data.ajaxPost();
		$.Hidemsg();
	});
});
