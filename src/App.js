import React, { Component } from 'react';
import { Route } from 'react-router-dom';
import './App.css';
import NavBar from './components/NavBar';
import Home from './components/Home';
import Media from './components/Media';
import Contact from './components/Contact';
import LoginForm from './components/LoginForm';

import axios from 'axios';
import AddPost from './components/AddPost';


class App extends Component {

  constructor() {
    super()
    this.state = {
      media: [],
      isLoading: true
    }

    this.getUser = this.getUser.bind(this)
    this.componentDidMount = this.componentDidMount.bind(this)
    this.updateUser = this.updateUser.bind(this)
  }

  componentDidMount() {
    this.getUser()
    this.getMedia()
    this.setState({isLoading: false})
  }

  updateUser (userObject) {
    this.setState(userObject)
  }


  getUser() {
    axios.get('/user/').then(response => {
      console.log('Get user response: ')
      console.log(response.data)
      if (response.data.user) {
        console.log('Get User: There is a user saved in the server session: ')

        this.setState({
          loggedIn: true,
          email: response.data.user.email
        })
      } else {
        console.log('Get user: no user');
        this.setState({
          loggedIn: false,
          email: null
        })
      }
    })
  }

  getMedia() {
    axios.get('/media/').then(response => {
      console.log('Get media response: ')
      console.log(response.data)
      if (response.data.media) {
        console.log('Get Media: There is a media saved in the server session: ')
          this.setState({
              media: response.data.media,
              isLoading: false
          })

      } else {
        console.log('Get media: no media');

      }
    })
  }

  render() {
    
    return (
      <div className="App">
        
        {/*<YouTube
          videoId="2g811Eo7K8U"
          opts={opts}
          onReady={this._onReady}
        />
        <SpotifyPlayer
          uri="spotify:album:4iMi0w7dp9d1PmYIpOHum8"
          size={size}
          view={view}
          theme={theme}
        />*/}
        <NavBar updateUser={this.updateUser} loggedIn={this.state.loggedIn}/>
        
        <div>
          <Route exact path="/" render={() =>
            <Home
              media={this.state.media}
              isLoading={this.state.isLoading}
            />}
          />
          <Route exact path="/media" component={Media} />
          <Route exact path="/contact" component={Contact}/>
          <Route exact path="/admin" render={() =>
            <LoginForm
              loggedIn={this.state.loggedIn}
              updateUser={this.updateUser}
            />}
          />
          <Route exact path="/add-post" component={AddPost}/>
        </div>
      </div>
    );
  }

  _onReady(event) {
    // access to player in all event handlers via event.target
    event.target.pauseVideo();
  }
}

export default App;
