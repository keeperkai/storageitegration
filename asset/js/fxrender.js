var i = 0;
self.onmessage = function(e){
  var input = e.data;
  /*
  console.log(input.target);
  for(var prop in input.target){
    console.log(prop);
    console.log(input.target[prop]);
  }
  */
  //console.log(input.target.material.constants);
  /*
  recieves a call to render something
  expected input:
  {
    
    target: //render target metrics
    {
      width,height,position
      material:{constants :{'ms_since_start':0.05,'noisetexture':{width,height,imagedata.data}}, shader:function(fin, constants)}//returns (r,g,b,a)
      id: // the id of this rendertarget
    }
  }
  structure of fin:
  {
    screenPos:{x,y}
    normalizedPos:{x,y}
  }
  */
  //expected data: {data:array to be assigned to imagedata.data, id: id of render target}
  var data = [];
  for(var j=0;j<input.target.height;++j){
    for(var i=0;i<input.target.width;++i){
      var offset = 4*(i+j*input.target.width);
      var fin = {
        screenPos: {x:i+input.target.position.x,y:j+input.target.position.y},
        normalizedPos: {x:i/input.target.width,y:j/input.target.height}
      };
      var color = pixelshaders[input.target.material.shader](fin, input.target.material.constants);
      data.push(color.r*255);
      data.push(color.g*255);
      data.push(color.b*255);
      data.push(color.a*255);
    }
  }
  postMessage( 
    {
      'data': data,
      'id': input.target.id
    }
  );
}
//shader functions
function tex2d(sampler, pos){
    pos.x = pos.x%1;
    pos.y = pos.y%1;
    var i = Math.floor(sampler.width*pos.x);
    var j = Math.floor(sampler.height*pos.y);
    var offset_start = 4*i+4*j*sampler.width;
    //console.log(sampler.data[offset_start]);
    var color = {r:sampler.data[offset_start]/255,g:sampler.data[offset_start+1]/255,b:sampler.data[offset_start+2]/255,a:sampler.data[offset_start+3]/255};
    //console.log(color.r+','+color.g+','+color.b+','+color.a);
    //console.log(color);
    return color;

}
var pixelshaders = {
  'ps_sincloud': ps_sincloud
};
function ps_sincloud(fin, constants){
    //var clouden = tex2d(constants.cloudgraph,{'x':x,'y':y}).r;
    var t = constants.ms_time;
    var clouden = tex2d(constants.noisetex, {x:fin.normalizedPos.x+0.00005*t,y:fin.normalizedPos.y+0.0005*t}).r;
    clouden += 0.5*tex2d(constants.noisetex, {x:2*(fin.normalizedPos.x+0.00005*t),y:2*(fin.normalizedPos.y)}).r;
    clouden += 0.25*tex2d(constants.noisetex, {x:4*(fin.normalizedPos.x),y:4*(fin.normalizedPos.y+0.0005*t)}).r;
    clouden /= 1.75;
    //console.log(constants.ms_time);
    var time_vary_en = Math.sin(clouden*2*Math.PI+t*0.0005);//t*0.0005
    //normalize
    var en = (time_vary_en+1)/2;
    //console.log(en);
    return {r:en,g:en,b:en,a:1};
    //return {x:0,y:0,z:0,a:1};
    
}
