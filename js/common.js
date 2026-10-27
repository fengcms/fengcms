/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 15:59:32
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
//提醒访问者使用的浏览器是IE6。如果不希望提示，则可以删除这一段即可。
$(document).ready(function(){
  $("#ifie6").click(function(){
  $(this).hide();
  });
});
document.writeln("<!--[if IE 6]>");
document.writeln("<div id=\"ifie6\">您使用的是14年前的古董——IE6浏览器，这是极其危险的。建议您升级浏览器到IE8。或者使用其他浏览器。点击此处可以关闭此提示</div>");
document.writeln("<![endif]-->");

//下拉菜单JS

function dropMenu(obj){
	$(obj).each(function(){
		var theSpan = $(this);
		var theMenu = theSpan.find(".submenu");
		var tarHeight = theMenu.height();
		theMenu.css({height:0,opacity:0});
		theSpan.hover(
			function(){
				$(this).addClass("nav_on");
				theMenu.stop().show().animate({height:tarHeight,opacity:1},400);
			},
			function(){
				$(this).removeClass("nav_on");
				theMenu.stop().animate({height:0,opacity:0},400,function(){
					$(this).css({display:"none"});
				});
			}
		);
	});
}

$(document).ready(function(){	
	dropMenu("#nav li");
});


//加入收藏
function AddFavorite(sURL, sTitle) {
		sURL = encodeURI(sURL); 
	try{   
		window.external.addFavorite(sURL, sTitle);   
	}catch(e) {   
		try{   
			window.sidebar.addPanel(sTitle, sURL, "");   
		}catch (e) {   
			alert("加入收藏失败，请使用Ctrl+D进行添加,或手动在浏览器里进行设置.");
		}   
	}
}
//设为首页
function SetHome(url){
	if (document.all) {
		document.body.style.behavior='url(#default#homepage)';
		   document.body.setHomePage(url);
	}else{
		alert("您好,您的浏览器不支持自动设置页面为首页功能,请您手动在浏览器里设置该页面为首页!");
	}
}
//首页幻灯
$(function(){
	var root = document.getElementById("home_hd");
	if (!root) return;
	var slides = Array.from(root.children).filter(function (node) {
		return node.tagName === "A" && node.querySelector("img");
	});
	if (!slides.length) return;
	root.classList.add("cms-home-slider");
	var caption = document.createElement("div");
	caption.className = "cms-slider-caption";
	root.appendChild(caption);
	var controls = document.createElement("div");
	controls.className = "cms-slider-controls";
	controls.setAttribute("aria-label", "焦点图切换");
	var buttons = slides.map(function (slide, index) {
		var button = document.createElement("button");
		button.type = "button";
		button.textContent = String(index + 1);
		button.setAttribute("aria-label", "显示第 " + (index + 1) + " 张焦点图");
		button.addEventListener("click", function () { show(index); start(); });
		controls.appendChild(button);
		return button;
	});
	if (slides.length > 1) root.appendChild(controls);
	var current = 0;
	var timer;
	function show(index) {
		current = index;
		slides.forEach(function (slide, position) { slide.hidden = position !== index; });
		caption.textContent = slides[index].querySelector("img").alt || "";
		buttons.forEach(function (button, position) {
			button.setAttribute("aria-current", position === index ? "true" : "false");
		});
	}
	function stop() { if (timer) clearInterval(timer); timer = null; }
	function start() {
		stop();
		if (slides.length > 1) timer = setInterval(function () {
			show((current + 1) % slides.length);
		}, 5000);
	}
	root.addEventListener("mouseenter", stop);
	root.addEventListener("mouseleave", start);
	root.addEventListener("focusin", stop);
	root.addEventListener("focusout", function (event) {
		if (!root.contains(event.relatedTarget)) start();
	});
	show(0);
	start();
});


//首页滚动图文
$(function(){
	if (!$.fn.kxbdSuperMarquee || !$('#home_guncon').length) return;
	$('#home_guncon').kxbdSuperMarquee({
		distance:350,
		time:3,
		btnGo:{left:'#gol',right:'#gor'},
		direction:'left'
	});
});



//gotop

window.onscroll = function(){ 
var t = document.documentElement.scrollTop || document.body.scrollTop; 
var top = document.getElementById("go_top");
if (top) top.className = t >= 200 ? "black" : "none";
}

//新闻内容评论切换
function swap_comment(n){
	for(var i=1;i<=5;i++){
		var curC=document.getElementById("comment_"+i);
		var curB=document.getElementById("comment_t"+i);
		if(n==i){
			curC.style.display="block";
			curB.className="active"
		}else{
			curC.style.display="none";
			curB.className="normal"
		}
	}
} 


