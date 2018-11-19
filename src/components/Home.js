import React, { Component } from 'react';
import MediaContent from './MediaContent';

class Home extends Component {

  constructor(props) {
    super(props)
    this.state = {
        isLoading: true
      }
}

  render() {
    
    return (
      <div className="Home">
        <div className="container main-content centered" role="main">
            <div className="home-heading">$LIZZATRISM</div>
            <p className="lead">
            The Official Website of The Washington Slizzards
            </p>
            <MediaContent media={this.props.media} isLoading={this.props.isLoading}/>
        </div>

      </div>
    );
  }

}

export default Home;
