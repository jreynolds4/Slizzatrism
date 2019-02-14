import React, { Component } from 'react';
import YouTube from 'react-youtube';
import SpotifyPlayer from 'react-spotify-player';
import ReactPlayer from 'react-player'
import axios from 'axios';

class MediaForm extends Component {

    constructor() {
        super()
        this.state = {
            title: '',
            url: '',
            artists: '',
            description: 'description',
            platform: '',
            urlError: '',
            spotifyTheme: 'black',
            displayName: '',
            redirectTo: null
        }
        this.handleSubmit = this.handleSubmit.bind(this)
        this.handleChange = this.handleChange.bind(this)
        this.handleURLChange = this.handleURLChange.bind(this)
        this.validURL = this.validURL.bind(this)
        this.displayYouTubePreview = this.displayYouTubePreview.bind(this)
        this.displaySpotifyPreview = this.displaySpotifyPreview.bind(this)

    }

    handleChange(event) {
        this.setState({
            [event.target.name]: event.target.value
        })
    }

    handleURLChange(event) {
        let platform

        if(event.target.value.includes('soundcloud')){
            platform = 'SoundCloud'
        } else if(event.target.value.includes('spotify')){
            platform = 'Spotify'
        } else if(event.target.value.includes('youtube')){
            platform = 'YouTube'
        }
        this.setState({
            url: event.target.value,
            platform: platform
        })
    }

    validURL(){
        return (this.state.url.includes('youtube') || this.state.url.includes('soundcloud') || this.state.url.includes('spotify'))
    }

    displayYouTubePreview() {

        const opts = {
            height: '390',
            width: '640',
            playerVars: { // https://developers.google.com/youtube/player_parameters
              autoplay: 0
            }
        };

        <YouTube
          videoId="2g811Eo7K8U"
          opts={opts}
          onReady={this._onReady}
        />
        
    }

    _onReady(event) {
        // access to player in all event handlers via event.target
        event.target.pauseVideo();
    }

    displaySpotifyPreview(){

        const size = {
            width: '100%',
            height: 300,
        };
        const view = 'list'; // or 'list'

        return (<SpotifyPlayer
          uri="spotify:album:4iMi0w7dp9d1PmYIpOHum8"
          size={size}
          view={view}
          theme={this.state.theme}
        />);
    }

    handleSubmit(event, ) {
        event.preventDefault()
        console.log('handleSubmit')

        if(!this.validURL){
            console.log('invalidURL')
            console.log(this.state)
            this.setState({
                urlError: 'Invalid URL!'
            })
        } else {
        axios
            .post('/media/', {
                title: this.state.title,
                url: this.state.url,
                artists: this.state.artists,
                description: this.state.description,
                platform: this.state.platform,
                spotifyTheme: this.state.spotifyTheme
            })
            .then(response => {
                console.log('media response: ')
                console.log(response)
                if (response.status === 200) {
                    this.setState({
                        redirectTo: '/'
                    })
                }
            }).catch(error => {
                console.log('media error: ')
                console.log(error);
                
            })
        }
    }

  render() {
    
    return (
      <div className="MediaForm">
            <h4>Add New Post</h4>
            <form>
                <div className="form-group">
                    <label for="exampleInputEmail1">Song Title</label>
                    <input className="form-control"
                                type="text"
                                name="title"
                                placeholder=""
                                value={this.state.title}
                                onChange={this.handleChange}
                                required
                    />
                </div>
                <div className="form-group">
                    <label for="exampleInputPassword1">Artist(s)</label>
                    <input className="form-control"
                            placeholder=""
                            type="artists"
                            name="artists"
                            value={this.state.artists}
                            onChange={this.handleChange}
                    />
                    <small id="artistsHelp" className="form-text text-muted">Enter music artists (separated by commas)</small>
                </div>
                <div className="form-group">
                    <small id="artistsHelp" className="form-text text-muted">{this.state.urlError}</small>
                    <label for="exampleInputPassword1">URL</label>
                    <input className="form-control"
                            placeholder="Enter the song link (SoundCloud, Spotify or YouTube)"
                            id="id"
                            type="url"
                            name="url"
                            value={this.state.url}
                            onChange={this.handleURLChange}
                    />
                    <small id="artistsHelp" className="form-text text-muted">(SoundCloud, Spotify, YouTube)</small>
                </div>
                {this.state.platform === 'Spotify' && 
                <div className="form-group row">
                    <label for="staticEmail" className="col-sm-1 col-form-label">Theme</label>
                    <div className="form-check form-check-inline">
                        <input className="form-check-input" type="radio" name="themeOptions" id="themeBlack" value="black" onChange={this.handleChange} checked/>
                        <label className="form-check-label" for="themeBlack">Black</label>
                    </div>
                    <div className="form-check form-check-inline">
                        <input className="form-check-input" type="radio" name="themeOptions" id="themeWhite" value="white" onChange={this.handleChange}/>
                        <label className="form-check-label" for="themeWhite">White</label>
                    </div>
                </div>
                }
                <div className="form-group">
                    <small id="displayName" className="form-text text-muted">{this.state.urlError}</small>
                    <label for="displayName">Display Name: </label>
                    <input className="form-control"
                            placeholder=""
                            id="id"
                            type="displayName"
                            name="displayName"
                            value={this.state.displayName}
                            onChange={this.handleChange}
                    />
                    <small id="displayName" className="form-text text-muted">How the title will display over the media content.</small>
                </div>
                <div className="form-group">
                    <label for="Description">Description (optional)</label>
                    <textarea className="form-control" id="Description" rows="3"></textarea>
                </div>
                <div className="form-group row">
                    <label for="staticEmail" className="col-sm-1 col-form-label">Preview:</label>
                </div>
                <div>{this.state.displayName}</div>
                <br />
                <p>{this.state.platform === 'Spotify' &&
                this.displaySpotifyPreview()}
                {this.state.platform !== 'Spotify' &&
                <ReactPlayer url={this.state.url} />}
                </p>
                <div>{this.state.description}</div>
                <br />
                <br />
                <div className="centered">
                    <button type="submit" onClick={this.handleSubmit} className="btn btn-primary">Submit</button>
                </div>
                <br />
                <br />
            </form>
      </div>
    );
  }

}

export default MediaForm;
