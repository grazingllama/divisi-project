import React from "react";
import { useTranslation } from "react-i18next";

function Settings() {
    const { t, i18n } = useTranslation();
    const currentBitrate = localStorage.getItem('preferredBitrate') || 'medium';

    // Add useEffect to restore language on mount
    React.useEffect(() => {
        const savedLanguage = localStorage.getItem('preferredLanguage');
        if (savedLanguage) {
            i18n.changeLanguage(savedLanguage);
        }
    }, [i18n]);

    const handleLanguageChange = (lang) => {
        i18n.changeLanguage(lang);
        localStorage.setItem('preferredLanguage', lang);
    };

    const handleBitrateChange = (bitrate) => {
        // Store current language before reload
        const currentLang = i18n.language;
        localStorage.setItem('preferredLanguage', currentLang);
        localStorage.setItem('preferredBitrate', bitrate);
        window.location.reload();
    };

    return (
        <div className="settings-container">
            <h1>{t('settings')}</h1>
            
            <div className="settings-section">
                <h2>{t('language')}</h2>
                <div className="language-options">
                    <button 
                        onClick={() => handleLanguageChange('de')}
                        className={i18n.language === 'de' ? 'active' : ''}
                    >
                        {t('german')}
                    </button>
                    <button 
                        onClick={() => handleLanguageChange('en')}
                        className={i18n.language === 'en' ? 'active' : ''}
                    >
                        {t('english')}
                    </button>
                </div>
            </div>

            <div className="settings-section">
                <h2>{t('bitrate')}</h2>
                <div className="bitrate-options">
                    <button 
                        onClick={() => handleBitrateChange('low')}
                        className={currentBitrate === 'low' ? 'active' : ''}
                    >
                        {t('low')}
                    </button>
                    <button 
                        onClick={() => handleBitrateChange('medium')}
                        className={currentBitrate === 'medium' ? 'active' : ''}
                    >
                        {t('medium')}
                    </button>
                    <button 
                        onClick={() => handleBitrateChange('lossless')}
                        className={currentBitrate === 'lossless' ? 'active' : ''}
                    >
                        {t('lossless')}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default Settings;