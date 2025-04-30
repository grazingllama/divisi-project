import React from "react";
import { useTranslation } from "react-i18next";

function Profile() {
    const { t } = useTranslation();
    return(
        <div>
            <h1>{t('profile')}</h1>
        </div>
    )
}

export default Profile;