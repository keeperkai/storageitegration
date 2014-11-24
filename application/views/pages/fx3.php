<button onclick='runfx();'>run fx effect</button>
<img id='image' height='300' width='300' src='<?php echo base_url();?>asset/img/cloudgraphnoseam-sharp.png' hidden></img>
<button class='btn btn-default btn-lg'>testing</button>
<div id='viewport' style='width:300px;height:300px;border:5px solid #d3d3d3;'></div>
<script>
function RenderSystem(rendertarget){
    this.appstart = (new Date()).getTime();
    this.rendertarget = rendertarget;
    this.renderer = new Worker('<?php echo base_url()."asset/js/fxrender.js";?>');
    var that = this;
    this.renderer.onmessage = function(e){
        that.rendertarget.setData(e.data.data);
        that.rendertarget.renderToAttachedDOM();
        that.renderLoop();
    }
    this.renderLoop = function(){
        var appstart = this.appstart;
        var rendertarget = this.rendertarget;
        //update globals
        var d = new Date();
        d = d.getTime();
        var time_since_start = d-appstart;
        var globals = {
            ms_time: time_since_start
        } 
        Material.UpdateGlobals(globals);
        //console.log("this is "+rendertarget.getClonableObject());
        //this.renderer.postMessage(JSON.stringify(rendertarget.getClonableObject()));
        this.renderer.postMessage({target:rendertarget.getClonableObject()});
        /* 
        this.renderer.postMessage(
        //JSON.stringify(
        
        {
            'target':{
                id:rendertarget.id,
                width:rendertarget.width,
                height:rendertarget.height,
                position:rendertarget.position,
                material:
                {
                    constants:rendertarget.material.constants,
                    shader:rendertarget.shader
                }
            }
        }
        //)
        
        );
        */
    }
    this.renderLoop();
}
function runfx(){
    var noise_sampler = new ImageSampler(document.getElementById('image'));
    var cloudmat = new Material({noisetex:noise_sampler}, 'ps_sincloud');
    var appstart = (new Date()).getTime();
    var rendertarget = new RenderTarget(300, 300, {x:0,y:0}, cloudmat);
    rendertarget.attachDOM(document.getElementById('viewport'));
    var rendersys = new RenderSystem(rendertarget);
}
/*
function renderLoop(appstart, rendertarget, renderer){
    //update globals
    var d = new Date();
    d = d.getTime();
    var time_since_start = d-appstart; 
    var globals = {
        ms_time: time_since_start
    }
    Material.UpdateGlobals(globals);
    
    renderer.postMessage({target:rendertarget);
    
    //setTimeout(function(){renderLoop(appstart, rendertarget, renderer);},10);
}
*/
//sampling functions

function GraphicsBuffer(width,height){
    this.canvas = document.createElement('canvas');
    this.canvas.width = width;
    this.canvas.height = height;
    this.canvas.style.visibility = 'hidden';
    this.context = this.canvas.getContext('2d');
}

function ImageSampler(img){
    this.buffer = new GraphicsBuffer(img.width,img.height);
    this.buffer.context.drawImage(img, 0, 0, img.width, img.height);
    this.data = this.buffer.context.getImageData(0,0,img.width,img.height).data;
    this.domImage = img;
    this.width = img.width;
    this.height = img.height;
    this.dirty = false;
}
ImageSampler.prototype.refresh = function(){
    //refresh the data after the buffer has been modified
    var imagedata = buffer.getImageData(0,0,this.width,this.height);
    this.data = imagedata.data;
    this.dirty = true;
}
ImageSampler.prototype.getClonableObject = function(){
    return {
        data: this.data,
        width: this.width,
        height: this.height
    };
}
function Material(constants, shadername){//shadername is a string
    this.constants = {};
    this.shader = shadername;
    for(var prop in constants){
        if(constants.hasOwnProperty(prop)){
            this.constants[prop] = constants[prop];
        }
    }
    Material.instances.push(this);
}
Material.instances = [];
Material.prototype.setProperty = function(name, value){
    this.constants[name] = value;
}
Material.UpdateGlobals = function(globals){
    for(var i=0;i<Material.instances.length;++i){
        var mat = Material.instances[i];
        for(var prop in globals){
            if(globals.hasOwnProperty(prop)){
                mat.setProperty(prop, globals[prop]);
            }
        }
    }
}
Material.prototype.getClonableObject = function(){
    var output_constants = {};
    for(var prop in this.constants){
        if(this.constants.hasOwnProperty(prop)){
            if(this.constants[prop] instanceof ImageSampler){
                output_constants[prop] = (this.constants[prop]).getClonableObject();
            }else{
                output_constants[prop] = this.constants[prop];
            }
        }
    }
    return {
        'constants': output_constants,
        'shader': this.shader
    };
}
function RenderTarget(width,height,position,material){//render target with no dom element attached
    this.buffer = new GraphicsBuffer(width,height);
    this.width = width;
    this.height = height;
    this.position = position;
    this.material = material;
    this.id = RenderTarget.Count++;
    this.attachedDOM = [];
}
RenderTarget.prototype.attachDOM = function(element){
    this.attachedDOM.push(element);
}
RenderTarget.prototype.detachDOM = function(element){
    this.attachedDOM.splice(this.attachedDOM.indexOf(element), 1);
}
RenderTarget.prototype.renderToAttachedDOM = function(){
    var dataurl = this.buffer.canvas.toDataURL();
    //console.log(dataurl);
    for(var i=0;i<this.attachedDOM.length;++i){
        var ele = this.attachedDOM[i];
        $(ele).css("background-image", "url('"+dataurl+"')");
    }
}
var showed = false;
RenderTarget.prototype.setData = function(data){
    //this.buffer.context.putImageData(data,0,0);
    /*
    if(!showed){
    for(var i=0;i<data.length;++i){
        console.log(i+' : '+data[i]);
    }
    }
    showed = true;
    */
    var imagedata = this.buffer.context.getImageData(0,0,this.buffer.canvas.width,this.buffer.canvas.height);
    //imagedata.data = data;
    for(var i=0;i<data.length;++i){
        imagedata.data[i] = data[i];
    }
    this.buffer.context.putImageData(imagedata, 0,0);
    
}
RenderTarget.Count = 0;
RenderTarget.prototype.getClonableObject = function(){
    return {
        width:this.width,
        height:this.height,
        position:this.position,
        material:this.material.getClonableObject(),
        id:this.id
    };
}
</script>