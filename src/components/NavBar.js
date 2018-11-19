import React, { Component } from 'react';
import './NavBar.css'

class NavBar extends Component {
  render() {
    
    return (
      <div className="NavBar">
        
        <nav className="navbar navbar-expand-lg">
            <button className="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarTogglerDemo03" aria-controls="navbarTogglerDemo03" aria-expanded="false" aria-label="Toggle navigation">
                <span className="navbar-toggler-icon"></span>
            </button>

            <div className="collapse navbar-collapse" id="navbarTogglerDemo03">
                <ul className="navbar-nav mr-auto mt-2 mt-lg-0 social-media-icons">
                    <li className="nav-item active">
                        <a className="nav-link" href="http://www.instagram.com/rasnebyu" target="_blank" rel="noopener noreferrer"><i className="fab fa-instagram"></i></a>
                    </li>
                    <li className="nav-item">
                        <a className="nav-link" href="http://www.soundcloud.com/rasnebyu" target="_blank" rel="noopener noreferrer"><i className="fab fa-soundcloud"></i></a>
                    </li>
                    <li className="nav-item active">
                        <a className="nav-link" href="https://www.youtube.com/user/LexThunderTV" target="_blank" rel="noopener noreferrer"><i className="fab fa-youtube"></i></a>
                    </li>
                    <li className="nav-item">
                        <a className="nav-link" href="http://www.twitter.com/rasnebyu" target="_blank" rel="noopener noreferrer"><i className="fab fa-twitter"></i></a>
                    </li>
                    <li className="nav-item">
                        <a className="nav-link" href="http://www.facebook.com/rasnebyu" target="_blank" rel="noopener noreferrer"><i className="fab fa-facebook-f"></i></a>
                    </li>
                </ul>
                <ul className="navbar-nav my-2 my-lg-0">
                    <li className="nav-item active">
                        <a className="nav-link" href="/">Home <span className="sr-only">(current)</span></a>
                    </li>
                    <li className="nav-item">
                        <a className="nav-link" href="/media">Media</a>
                    </li>
                    <li className="nav-item">
                        <a className="nav-link" href="/slizzards">Washington Slizzards</a>
                    </li>
                    <li className="nav-item">
                        <a className="nav-link disabled" href="/shop">$lizz $hop (coming soon!)</a>
                    </li>
                </ul>
            </div>
        </nav>
      </div>
    );
  }

}

export default NavBar;
