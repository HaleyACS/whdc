// Use with this line
// <a href="javascript:hideshow(document.getElementById('adiv'))">Click here</a>
//
// Insert this to be used
// <div id="adiv" style="font:24px bold; display: block">Now you see me</div>


function hideshow(which){
    if (!document.getElementById)
        return
    if (which.style.display=="none")
        which.style.display="block"
    else
        which.style.display="none"
}
