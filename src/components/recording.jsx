import React, { useEffect, useState, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import MusicPlayer from './player';
import FormattedText from './formatted-text';
import '../App.css';
import { useTranslation } from 'react-i18next';

const RecordingPage = ({ playRecording }) => {
  const { id: recordingId } = useParams();
  const [recordingData, setRecordingData] = useState(null);
  const [error, setError] = useState(null);
  const recordingPageRef = useRef(null);
  const [recordingPageRect, setRecordingPageRect] = useState(null);
  const { t } = useTranslation();

  useEffect(() => {
    if (!recordingId) {
      setError("No recording ID provided.");
      return;
    }

    fetch(`https://divisi-project.de/getRecordingData.php?recording_id=${recordingId}`)
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then(data => {
        console.log('API Response:', data); // Add this debug log
        if (data.error) {
          throw new Error(data.error);
        }
        // Format the data to match the nested structure from the API
        const formattedData = {
          recording: {
            ...data.recording,
            piece_name: data.recording?.piece?.name || 'Untitled Piece',
            catalogue_number: data.recording?.piece?.catalogue_number || '',
            composer_name: data.recording?.composer?.name || 'Unknown Composer',
            composer_id: data.recording?.composer?.id || null,
            img: data.recording?.img || null,
            recording_date: data.recording?.recording_date || null,
            use_instrument_groups: data.recording?.use_instrument_groups || false
          },
          artists: Array.isArray(data.artists) ? data.artists.map(artist => ({
            id: artist.id || null,
            name: artist.name || 'Unknown Artist',
            info: artist.info || ''
          })) : [],
          instruments: {
            ...data.instruments,
            use_instrument_groups: data.instruments?.use_instrument_groups || false,
            all_instruments: data.instruments?.all_instruments || []
          }
        };
        setRecordingData(formattedData);
      })
      .catch(error => {
        console.error('Error fetching recording data:', error);
        setError(error.message);
      });
  }, [recordingId]);

  useEffect(() => {
    if (recordingPageRef.current) {
      setRecordingPageRect(recordingPageRef.current.getBoundingClientRect());
    }
  }, [recordingData]);

  if (error) {
    return <div>{t('error')} {error}</div>;
  }

  if (!recordingData) {
    return <div>{t('loading')}</div>;
  }

  const newDate = new Date(recordingData.recording.recording_date);

  return (
    <div className="page-content" ref={recordingPageRef}>
      <div className="recording-info">
        <img
          className="recording-img"
          src={recordingData.recording.img || 'https://divisi-project.de/media/photo/no-image.png'}
          alt="Recording cover"
        />
        <div className="recording-title">
          {recordingData.recording.composer_id ? (
            <Link to={`/composer/${recordingData.recording.composer_id}`}>
              <div className="rec-composer-name">
                <h4>{recordingData.recording.composer_name}</h4>
              </div>
            </Link>
          ) : (
            <div className="rec-composer-name">
              <h4>{recordingData.recording.composer_name}</h4>
            </div>
          )}
          <h1>
            <span className="rec_name">
              <FormattedText htmlContent={recordingData.recording.piece_name || 'Untitled Piece'} />
              {recordingData.recording.catalogue_number && ', '}
            </span>
            {recordingData.recording.catalogue_number && (
              <span className="rec_catnum">{recordingData.recording.catalogue_number}</span>
            )}
          </h1>
          <div className="rec-artists-name">
            {recordingData.artists.length > 0 ? (
              recordingData.artists.map((artist, index) => (
                <React.Fragment key={artist.id}>
                  <Link to={`/artist/${artist.id}`} className="rec-single-artist-name">
                    {artist.name}
                  </Link>
                  {index < recordingData.artists.length - 1 && <span className="artist-separator"> · </span>}
                </React.Fragment>
              ))
            ) : (
              <p>{t('no-artist')}</p>
            )}
          </div>
          <p className="rec-date">{newDate.getFullYear()}</p>
        </div>
      </div>
      
      {recordingData.instruments && Object.keys(recordingData.instruments).length > 0 ? (
        <MusicPlayer 
          recordingData={recordingData} 
          recordingPageRect={recordingPageRect}
          useInstrumentGroups={recordingData.recording.use_instrument_groups}
        />
      ) : (
        <p>{t('no-data')}</p>
      )}
    </div>
  );
};

export default RecordingPage;