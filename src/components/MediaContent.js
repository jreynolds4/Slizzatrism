import React, { Component } from 'react';
import ReactPlayer from 'react-player'

class MediaContent extends Component {

    constructor(props) {
        super(props)
        this.state = {
            title: '',
            url: '',
            artists: '',
            description: '',
            platform: '',
            urlError: '',
            spotifyTheme: 'black',
            displayName: '',
            redirectTo: null,
        }
    }

    

    render() {
/*
        const opts = {
        height: '390',
        width: '640',
        playerVars: { // https://developers.google.com/youtube/player_parameters
            autoplay: 0
        }
        };

        const size = {
        width: '100%',
        height: 300,
        };
        const view = 'list'; // or 'list'
        const theme = 'black'; // or 'white'
*/
        return (
        <div className="MediaContent centered">
                {this.props.media.map(song => 
                    <div>{song.artists} - {song.title}
                        {song.platform === "SoundCloud" &&
                            <ReactPlayer url={song.url} />
                        }
                    </div>
                )}
        </div>
        );
    }

    _onReady(event) {
        // access to player in all event handlers via event.target
        event.target.pauseVideo();
    }
}

export default MediaContent;
