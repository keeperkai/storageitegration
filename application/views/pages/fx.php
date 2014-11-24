<input id='fileinput' type='file'/>
<button onclick='runfx();'>load image</button>
<button onclick='applytobackground();'>apply to background</button>
<img id='image' height='300' width='300' hidden></img>
<canvas id='sampler' height='300' width='300'></canvas>
<canvas id='renderer' height='300' width='300' style='border:5px solid #d3d3d3;'>your browser doesn't support</canvas>
<script>
var setbackground = false;
function applytobackground(){
    setbackground = true;
}
function runfx(){
    var canvas = document.getElementById('renderer');
    var context = canvas.getContext('2d');
    var sampler = document.getElementById('sampler');
    var sampler_context = sampler.getContext('2d');
    var file = document.getElementById('fileinput').files[0];
    var reader = new FileReader();
    reader.onload = function(){
        var image_data = reader.result;
        var img = document.getElementById('image');
        //console.log(image_preview);
        img.src = image_data;
        sampler_context.drawImage(img, 0, 0, img.width, img.height);
        var realsampler = {width:img.width, height:img.height, context: sampler_context};
        var app_start_time = new Date();
        app_start_time = app_start_time.getTime();
        //setTimeout(function(){renderLoop(realsampler, app_start_time, canvas, context);},1000);
        renderLoop(realsampler, app_start_time, canvas, context);
        /*
        setTimeout(function(){
            var d = new Date();
            d = d.getTime();
            var constants = {
                cloudgraph: realsampler,
                ms_time: d-app_start_time
            };
            var renderdata = context.getImageData(0,0,canvas.width,canvas.height);
            for(var i=0;i<canvas.width;++i){
                for(var j=0;j<canvas.height;++j){
                    var offset_start = 4*i*canvas.width+4*j;
                    var psx = i/canvas.width;
                    var psy = j/canvas.height;
                    var c = ps_sincloud(psx,psy,constants);
                    renderdata.data[offset_start]=c.r;
                    renderdata.data[offset_start+1]=c.g;
                    renderdata.data[offset_start+2]=c.b;
                    renderdata.data[offset_start+3]=c.a;
                }
            }
            context.putImageData(0,0,renderdata);
            
        
        
        }, 200);
        */
    }
    reader.readAsDataURL(file);
}
function renderLoop(realsampler, app_start_time, canvas, context){
    var d = new Date();
    d = d.getTime();
    var constants = {
        cloudgraph: realsampler,
        ms_time: d-app_start_time,
        fastgraph: realsampler.context.getImageData(0,0,canvas.width,canvas.height)
    };
    var renderdata = context.getImageData(0,0,canvas.width,canvas.height);
    for(var i=0;i<canvas.width;++i){
        //console.log('i='+i);
        for(var j=0;j<canvas.height;++j){
            
            var offset_start = 4*i+4*j*canvas.width;
            var psx = i/canvas.width;
            var psy = j/canvas.height;
            var c = ps_sincloud(psx,psy,constants);
            
            
            renderdata.data[offset_start]=c.r*255;
            renderdata.data[offset_start+1]=c.g*255;
            renderdata.data[offset_start+2]=c.b*255;
            renderdata.data[offset_start+3]=c.a*255;
        }
    }
    
    /*
    for(var i=0;i<renderdata.data.length;++i){
        renderdata.data[i]=0;
        if(i%4 == 3){
            renderdata.data[i]=255;
        }
    }
    */
    //console.log(constants.fastgraph.data[0]);
    
    context.putImageData(renderdata,0,0);
    if(setbackground){
        var dataurl = canvas.toDataURL();
        $(document.body).css("background-image", "url('"+dataurl+"')");
    }
    //alert('called');
    setTimeout(function(){renderLoop(realsampler, app_start_time, canvas, context);},10);
}
function tex2d(sampler,pos){
    var data = sampler.context.getImageData(Math.round(sampler.width*pos.x),Math.round(sampler.height*pos.y),1,1).data;
    var color = {r:data[0],g:data[1],b:data[2],a:data[3]};
    return color;
}
function tex2dfast(imagedata, sampler, pos){
    var i = Math.floor(sampler.width*pos.x);
    var j = Math.floor(sampler.height*pos.y);
    var offset_start = 4*i+4*j*sampler.width;
    var color = {r:imagedata.data[offset_start]/255,g:imagedata.data[offset_start+1]/255,b:imagedata.data[offset_start+2]/255,a:imagedata.data[offset_start+3]/255};
    //console.log(color.r+','+color.g+','+color.b+','+color.a);
    return color;

}
function ps_sincloud(x, y, constants){
    //var clouden = tex2d(constants.cloudgraph,{'x':x,'y':y}).r;
    var t = constants.ms_time;
    var clouden = tex2dfast(constants.fastgraph, constants.cloudgraph,{'x':(x+t*0.00005)%1,'y':(y+t*0.00005)%1}).r;
    clouden += 0.5*tex2dfast(constants.fastgraph, constants.cloudgraph,{'x':((x+t*0.00005)*2)%1,'y':(y*2)%1}).r;
    clouden += 0.25*tex2dfast(constants.fastgraph, constants.cloudgraph,{'x':(x*4)%1,'y':((y+t*0.00005)*4)%1}).r;
    clouden /= 1.75;
    
    var time_vary_en = Math.sin(clouden*4*Math.PI+t*0.0005);//
    //normalize
    var en = (time_vary_en+1)/2;
    //console.log(en);
    return {r:en,g:en,b:en,a:1};
    //return {x:0,y:0,z:0,a:1};
    
}
</script>