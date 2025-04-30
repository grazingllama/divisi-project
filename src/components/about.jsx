import React from "react";
import CollMusLogo from "../media/photo/collmus-logo-white.png";
import MuWiTueLogo from "../media/photo/muwi-logo-white.png";
import { useTranslation } from "react-i18next";

function About() {
    const { t } = useTranslation();
    return(
        <div className="page-content">
        <h1>{t('about')}</h1>
        <p>
        <i>divisi</i>  {t('about-p-1')} <br/><br/>
        {t('about-p-2')} <br/><br/>
        <i>divisi</i>  {t('about-p-3')}</p>

        <ul className="logos">
            <li><a href="https://www.uni-tuebingen.de/musik"><img src={MuWiTueLogo}/></a></li>
            <li><a href="https://www.uni-tuebingen.de/collegium"><img src={CollMusLogo}/></a></li>
        </ul>
        </div>
    ) 

}

export default About;