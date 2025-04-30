import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

function ArtistComposerBlock({ name, category, id }) {
    const { t } = useTranslation();
    return (
        <div className="artistComposerContainer">
            <div className="artistComposerName">
                {/* Link to the artist or composer page */}
                <h4>
                    <Link to={`/${category}/${id}`}>{name}</Link>
                </h4>
            </div>
            <div className="category">
                <p>{category === 'artist' ? t('artist') : t('composer')}</p>
            </div>
        </div>
    );
}

export default ArtistComposerBlock;
