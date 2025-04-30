import React, { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import RecordingBlock from "./recording-block";
import { useTranslation } from "react-i18next";

const ArtistPage = () => {
  const { id: artistId } = useParams(); // Extract `id` from the URL
  const [artistData, setArtistData] = useState(null);
  const [error, setError] = useState(null);
  const { t } = useTranslation();

  useEffect(() => {
    if (!artistId) {
      setError("No artist ID provided");
      return;
    }

    // Fetch artist data from the API
    fetch(`https://divisi-project.de/getArtistData.php?artist_id=${artistId}`)
      .then((response) => {
        if (!response.ok) {
          throw new Error("Network response was not ok");
        }
        return response.json();
      })
      .then((data) => {
        if (data.error) {
          throw new Error(data.error);
        }
        setArtistData(data);
      })
      .catch((error) => {
        console.error("Error fetching artist data:", error);
        setError(error.message);
      });
  }, [artistId]);

  if (error) {
    return <div>{t('error')} {error}</div>;
  }

  if (!artistData) {
    return <div>{t('loading')}</div>;
  }

  return (
    <div className="page-content">
      <h1>{artistData.artist.name}</h1>
      <div>
        <ul className="recordings-list">
          {artistData.recordings.map((recording) => (
            <li key={recording.recording_id}>
              <RecordingBlock
                pieceName={recording.piece_name}
                catalogueNumber={recording.catalogue_number}
                composerName={recording.composer_name}
                composerId={recording.composer_id} // Ensure this is present
                recordingId={recording.recording_id}
                img={recording.img}
                artistNames={recording.artists.map((artist) => artist.name)}
                artistIds={recording.artists.map((artist) => artist.id)}
              />
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
};

export default ArtistPage;
