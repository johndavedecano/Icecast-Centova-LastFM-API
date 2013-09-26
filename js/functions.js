function getResponse(data){
    console.log(data);
    if(data.info.status == 'OFF AIR'){
         jQuery('#now-playing-text').text('Station is currently offline.');
         jQuery('#onair').css('background-color','#333');
         jQuery('#onair').text('OFF AIR');
    }else{
        jQuery('#onair').text("ON AIR");
        jQuery('#onair').css('background-color','darkred');
        jQuery('#stream-meta-title').text(data.info.title);
        
        var audioPlayer = document.getElementById('audio-player');
        
        if(!audioPlayer.paused){
            audioPlayer.play();
        }
        
        if(data.info.song.length == 0){
            jQuery('#now-playing-text').text('Station is online.');
        }else{
            jQuery('#now-playing-text').text('Now Playing');
        }
        if(data.info.song.length != 0){
            jQuery('#now-playing-track').text(data.info.song);
        }else{
            jQuery('#now-playing-track').text(data.track.name);
        }
        if(data.info.artist.length != 0){
            jQuery('#now-playing-artist').text(data.info.artist);
        }else{
            jQuery('#now-playing-artist').text(data.track.artist);
        }
        if(data.track.title.length != 0){
            jQuery('#now-playing-album').text(data.track.title);
        }else{
            jQuery('#now-playing-album').text("");
        }
        if(data.track.covers != 0){
            jQuery('#now-playing-img').attr('src',data.track.covers[2]);
        }else{
            jQuery('#now-playing-img').attr('src','./images/artwork.png');
        }

    }
}
function nowplaying(){
    $.ajax({
        timeout: 5000,
        url: "./current_track.php",
        cache: false,
        success:getResponse
    });
}
nowplaying();
setInterval( "nowplaying()", 10000 );

jQuery(document).ready(function() {
    jQuery('#mycarousel').jcarousel();
    jQuery('.tooltip').tooltipster();
    //jQuery('.scroll-pane').jScrollPane();
});
audiojs.events.ready(function() {
    audiojs.createAll();
});