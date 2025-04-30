import { Link } from 'react-router-dom';
import { useViewport } from '../App';
import FormattedText from './formatted-text';
import { useTranslation } from 'react-i18next';

const RecordingBlock = ({ pieceName, catalogueNumber, composerName, composerId, recordingId, artistNames = [], artistIds = [], img }) => {
    // Log the props passed to RecordingBlock for debugging
    console.log("RecordingBlock props:", { pieceName, catalogueNumber, composerName, composerId, artistNames, artistIds, img });

    const { t } = useTranslation();
    const { width } = useViewport();
    const breakpoint = 620;

    // Map artist names and IDs safely
    const artists = Array.isArray(artistNames) && artistNames.length > 0
        ? artistNames.map((name, index) => ({
            name,
            id: artistIds[index] || null
        }))
        : [{ name: t('unknown-artist'), id: null }];

    function catNumNbsp(catalogueNumber) {
        if (!catalogueNumber) return ""; // Handle undefined or null inputs
        return catalogueNumber.replace(/ /g, "\u00A0"); // Replace spaces with non-breaking spaces
      }
      
    const formattedCatalogueNumber = catNumNbsp(catalogueNumber);

    return width < breakpoint ? (
        <div className="recording-container">
            <Link to={`/recording/${recordingId}`} className="single-recording">
                <div className="recording-img-container">
                    <img
                        className="recording-img"
                        src={img || 'https://divisi-project.de/media/photo/no-image.png'}
                        alt="Recording cover" />
                </div>
                <div className="recordingInfo">
                    <div className="pieceName">
                        <h4>
                            <Link to={`/recording/${recordingId}`}>
                                <FormattedText htmlContent={pieceName} />, {formattedCatalogueNumber}
                            </Link>
                        </h4>
                    </div>
                    <div className="listComposerAndArtists">
                        <p>
                            {composerId ? (
                                <span>{composerName}</span>
                            ) : (
                                <span>{t('unknown-composer')}</span>
                            )}
                            {' · '}
                            {artists.map((artist, index) => (
                                <span key={artist.id || index}>
                                    {artist.id ? (
                                        <span>{artist.name}</span>
                                    ) : (
                                        <span>{t('unknown-artist')}</span>
                                    )}
                                    {index < artists.length - 1 && ', '}
                                </span>
                            ))}
                        </p>
                    </div>
                </div>
            </Link>
        </div>
    ) : (
        <div className="recording-container">
            <Link to={`/recording/${recordingId}`} className="single-recording">
                <div className="recording-img-container">
                    <img
                        className="recording-img"
                        src={img || 'https://divisi-project.de/media/photo/no-image.png'}
                        alt="Recording cover" />
                </div>
                <div className="recordingInfo">
                    <div className="pieceName">
                        <h4>
                            <Link to={`/recording/${recordingId}`}>
                                <FormattedText htmlContent={pieceName} />, {catalogueNumber}
                            </Link>
                        </h4>
                    </div>
                    <div className="listComposerAndArtists">
                        <p>
                            {composerId ? (
                                <Link to={`/composer/${composerId}`}>{composerName}</Link>
                            ) : (
                                <span>{t('unknown-composer')}</span>
                            )}
                            {' · '}
                            {artists.map((artist, index) => (
                                <span key={artist.id || index}>
                                    {artist.id ? (
                                        <Link to={`/artist/${artist.id}`}>{artist.name}</Link>
                                    ) : (
                                        <span>{t('unknown-artist')}</span>
                                    )}
                                    {index < artists.length - 1 && ', '}
                                </span>
                            ))}
                        </p>
                    </div>
                </div>
            </Link>
        </div>
    );
};

export default RecordingBlock;
