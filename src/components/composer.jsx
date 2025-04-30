import React, { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import RecordingBlock from "./recording-block";
import { useTranslation } from "react-i18next";

const ComposerPage = () => {
  const { id: composerId } = useParams(); // Use the `id` parameter from the route
  const [composerData, setComposerData] = useState(null);
  const [error, setError] = useState(null);
  const { t } = useTranslation();

  useEffect(() => {
    if (!composerId) {
      setError("No composer ID provided");
      return;
    }

    // Fetch composer data from the API
    fetch(
      `https://divisi-project.de/getComposerData.php?composer_id=${composerId}`
    )
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
        setComposerData(data);
      })
      .catch((error) => {
        console.error("Error fetching composer data:", error);
        setError(error.message);
      });
  }, [composerId]);

  if (error) {
    return <div>{t('error')} {error}</div>;
  }

  if (!composerData) {
    return <div>{t('loading')}</div>;
  }

  return (
    <div className="page-content">
      <h1>{composerData.composer.name}</h1>
      <ul className="recordings-list">
        {composerData.pieces.map((piece) => (
          <li key={piece.piece_id}>
            <RecordingBlock
              pieceName={piece.piece_name}
              catalogueNumber={piece.catalogue_number}
              composerName={composerData.composer.name}
              composerId={composerData.composer.id}
              recordingId={piece.recording_id}
              img={piece.img}
              artistNames={piece.artists.map((artist) => artist.name)}
              artistIds={piece.artists.map((artist) => artist.id)}
            />
          </li>
        ))}
      </ul>
    </div>
  );
};

export default ComposerPage;
