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
                    <div>
                        <div className="row centered divider">
                            <div className="col-sm-3"></div>
                            <div className="col-sm-6"><hr></hr></div>
                            <div className="col-sm-3"></div>
                        </div>
                        <div>{song.artists} - {song.title}</div>
                        <p></p>
                        <div className="row">
                            <div className="col-sm-3"></div>
                            <div className="col-sm-6">
                                {//song.platform === "SoundCloud" &&
                                <ReactPlayer className="centered" url={song.url} width="100%" />}
                            </div>
                            <div className="col-sm-3"></div>
                        </div>
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
