import React, { Component } from 'react';
import MediaForm from './MediaForm'

class AddPost extends Component {
  render() {
    
    return (
      <div className="AddPost">
        <div class="container main-content centered" role="main">
            <div class="home-heading">$LIZZATRISM</div>
            <p class="lead">
            The Official Website of The Washington Slizzards
            </p>
        </div>
        <div className='container'>
            <MediaForm />
        </div>
      </div>
    );
  }

}

export default AddPost;
