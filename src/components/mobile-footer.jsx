import React from "react";
import { Link } from 'react-router-dom';
import '../App.css';
import HomeIcon from "../media/icons/home.svg";
import HomeFillIcon from "../media/icons/home-fill.svg";
import RecordingsIcon from "../media/icons/music-notes.svg";
import RecordingsFillIcon from "../media/icons/music-notes-fill.svg";
import AboutIcon from "../media/icons/info.svg";
import AboutFillIcon from "../media/icons/info-fill.svg";
import SearchIcon from "../media/icons/search.svg";
import SearchFillIcon from "../media/icons/search-fill.svg";
import Home from "./home";
import Recordings from "./recordings";
import About from "./about";
import SearchPage from "./search";
import { useTranslation } from "react-i18next";

function MobileFooter({ activePage, setActivePage, setContent }) {
    const { t } = useTranslation();

    const handleHomeClick = () => {
        setActivePage('home');
        setContent(<Home />); // Replace with your Home component
    };

    const handleRecordingsClick = () => {
        setActivePage('recordings');
        setContent(<Recordings />); // Replace with your Recordings component
    };

    const handleSearchPageClick = () => {
        setActivePage('searchpage');
        setContent(<SearchPage />);
    }

    const handleAboutClick = () => {
        setActivePage('about');
        setContent(<About />); // Replace with your About component
    };

    return(
        <div className="mobile-footer">
            <nav>
                <ul>
                    <li className={activePage === 'home' ? 'active' : ''}>
                        <Link to="/" onClick={handleHomeClick}>
                            <img src={activePage === 'home' ? HomeFillIcon : HomeIcon} alt={t('home')}/>
                            {t('home')}
                        </Link>
                    </li>
                    <li className={activePage === 'recordings' ? 'active' : ''}>
                        <Link to="/recordings" onClick={handleRecordingsClick}>
                            <img src={activePage === 'recordings' ? RecordingsFillIcon : RecordingsIcon} alt={t('recordings')}/>
                            {t('recordings')}
                        </Link>
                    </li>
                    <li className={activePage === 'searchpage' ? 'active' : ''}>
                        <Link to ="/search" onClick={handleSearchPageClick}>
                            <img src={activePage === 'searchpage' ? SearchFillIcon : SearchIcon} alt={t('searchpage')}/>
                            {t('searchpage')}
                        </Link>
                    </li>
                    <li className={activePage === 'about' ? 'active' : ''}>
                        <Link to="/about" onClick={handleAboutClick}>
                            <img src={activePage === 'about' ? AboutFillIcon : AboutIcon} alt={t('about')}/>
                            {t('about')}
                        </Link>
                    </li>
                </ul>
            </nav>
        </div>
    )
}

export default MobileFooter;