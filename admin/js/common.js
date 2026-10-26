function gets(string,vals){
	var str=document.getElementById(string);
	if(string!="tags"){
		return str.value=vals.innerHTML;
	}else{
	if(str.value==""){
		return str.value+=vals.innerHTML;		
	}
		return str.value+=","+vals.innerHTML;
	}
}

