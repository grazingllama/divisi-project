import React, { useState, useEffect } from "react"; // Add useEffect
import { useNavigate, useLocation } from "react-router-dom"; // Add useLocation for React Router
import ProfileIcon from '../media/icons/person.svg';
import ProfileFillIcon from '../media/icons/person-fill.svg';
import SettingsIcon from '../media/icons/settings.svg';
import SettingsFillIcon from '../media/icons/settings-fill.svg';
import SearchIcon from '../media/icons/search.svg';
import DivisiLogo from '../media/photo/logo-divisi.svg';
import '../App.css';
import { useTranslation } from "react-i18next";

function Header({ activePage, setActivePage }) {
    const navigate = useNavigate();
    const location = useLocation(); // Hook to get the current location
    const [searchTerm, setSearchTerm] = useState('');
    const { t } = useTranslation();

    useEffect(() => {
        if (location.pathname.startsWith("/search")) {
            const queryParams = new URLSearchParams(location.search);
            const query = queryParams.get("query");
            if (query) {
                setSearchTerm(query); // Populate search bar with the query
            }
        }
    }, [location]);

    const handleProfileClick = () => {
        setActivePage('profile');
        navigate('/profile');
    };

    const handleSettingsClick = () => {
        setActivePage('settings');
        navigate('/settings');
    };

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        if (searchTerm.trim()) {
            navigate(`/search?query=${encodeURIComponent(searchTerm)}`);
        }
    };

    return (
        <header>
            <div className="logo">
                <a href="/">
                    <img src={DivisiLogo} alt="divisi" />
                </a>
            </div>
            <div className="search">
                <form onSubmit={handleSearchSubmit}>
                    <input
                        type="text"
                        placeholder={t('search')}
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                    />
                    <button type="submit">
                        <img src={SearchIcon} alt={t('search')} />
                    </button>
                </form>
            </div>
            <div className="hdr-btns">
                <div className="profile-btn" onClick={handleProfileClick}>
                    <img src={activePage === 'profile' ? ProfileFillIcon : ProfileIcon} alt={t('profile')} />
                </div>
                <div className="settings-btn" onClick={handleSettingsClick}>
                    <img src={activePage === 'settings' ? SettingsFillIcon : SettingsIcon} alt={t('settings')} />
                </div>
            </div>
        </header>
    );
}

export default Header;