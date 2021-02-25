<?php
namespace exface\Core\Widgets;

/**
 * Plays a video from a URI in its `value` or data of its `attribute_alias`.
 *
 * @author Andrej Kabachnik
 *        
 */
class Video extends Image
{
    private $autoplay = false;
    
    private $showControls = true;
    
    private $loop = false;
    
    private $muted = false;
    
    private $mimeType = null;
    
    private $thumbnailFromSecond = null;
    
    public function getAutoplay() : bool
    {
        return $this->autoplay;
    }
    
    /**
     * Set to TRUE to automatically play the video when the page is loaded
     * 
     * @uxon-property autoplay
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return Video
     */
    public function setAutoplay(bool $value) : Video
    {
        $this->autoplay = $value;
        return $this;
    }
    
    public function getShowControls() : bool
    {
        return $this->showControls;
    }
    
    /**
     * Set to FALSE to hide video player controls like play, pause, etc.
     *
     * @uxon-property show_controls
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $value
     * @return Video
     */
    public function setShowControls(bool $value) : Video
    {
        $this->showControls = $value;
        return $this;
    }
    
    public function getLoop() : bool
    {
        return $this->loop;
    }
    
    /**
     * Set to TRUE to strat playing from the beginning once the video has ended
     *
     * @uxon-property loop
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return Video
     */
    public function setLoop(bool $value) : Video
    {
        $this->loop = $value;
        return $this;
    }
    
    public function getMuted() : bool
    {
        return $this->muted;
    }
    
    /**
     * Set to TRUE to disable sound initially
     *
     * @uxon-property muted
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return Video
     */
    public function setMuted(bool $value) : Video
    {
        $this->muted = $value;
        return $this;
    }    
    
    /**
     * 
     * @return string|NULL
     */
    public function getMimeType() : ?string
    {
        return $this->mimeType;
    }
    
    /**
     * The mime type of the video: e.g. `video/mp4`
     *
     * @uxon-property mime_type
     * @uxon-type string
     *
     * @param bool $value
     * @return Video
     */
    public function setMimeType(string $value) : Video
    {
        $this->mimeType = $value;
        return $this;
    }
    
    /**
     * 
     * @return float|NULL
     */
    public function getThumbnailFromSecond() : ?float
    {
        return $this->thumbnailFromSecond;
    }
    
    /**
     * Generate the thumbnail from the video picture at a given time in seconds.
     * 
     * If `autoplay` is not enabled explicitly, a thumbnail will be shown in place of the
     * video and a button to start playback. This thumbnail can be determined automatically
     * (typically the first frame of the video) or taken from a specified position in the
     * video: e.g. `thumbnail_from_second:5.5` will display the snapshot a 5.5s of the video
     * as thumbnail.
     * 
     * @uxon-property thumbnail_from_second
     * @uxon-type number     * 
     * 
     * @param float $value
     * @return Video
     */
    public function setThumbnailFromSecond(float $value) : Video
    {
        $this->thumbnailFromSecond = $value;
        return $this;
    }    
}