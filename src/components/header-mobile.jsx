import React, { useState } from "react";
import { useNavigate } from "react-router-dom"; // Import useNavigate for React Router v6
import ProfileIcon from '../media/icons/person.svg';
import ProfileFillIcon from '../media/icons/person-fill.svg';
import SettingsIcon from '../media/icons/settings.svg';
import SettingsFillIcon from '../media/icons/settings-fill.svg';
import DivisiLogo from '../media/photo/logo-divisi.svg';
import '../App.css';
import { useTranslation } from 'react-i18next';

function MobileHeader({ activePage, setActivePage}) {
    const navigate = useNavigate();
    const { t } = useTranslation();
    const handleProfileClick = () => {
        setActivePage('profile');
        navigate('/profile'); // Assuming Profile is a component you want to render
    };

    const handleSettingsClick = () => {
        setActivePage('settings');
        navigate('/settings'); // Assuming Settings is a component you want to render
    };

    return(
        <header>
            <div className="logo">
                <a href="/">
                    <img src={DivisiLogo} alt="divisi"/>
                </a>
            </div>
            <div className="hdr-btns">
                <div className="profile-btn" onClick={handleProfileClick}>
                    <img src={activePage === 'profile' ? ProfileFillIcon : ProfileIcon} alt={t('profile')}/>
                </div>
                <div className="settings-btn" onClick={handleSettingsClick}>
                    <img src={activePage === 'settings' ? SettingsFillIcon : SettingsIcon} alt={t('settings')}/>
                </div>
            </div>
        </header>
    )
};

export default MobileHeader;