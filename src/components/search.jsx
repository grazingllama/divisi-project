import React, { useState, useEffect } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import Fuse from "fuse.js";
import SearchIcon from "../media/icons/search.svg";
import ArtistComposerBlock from "./artistcomposer-block";
import RecordingBlock from "./recording-block";
import "../App.css";
import { useTranslation } from "react-i18next";

function SearchPage() {
  const location = useLocation();
  const queryParams = new URLSearchParams(location.search);
  const navigate = useNavigate();
  const { t } = useTranslation();

  const initialQuery = queryParams.get("query") || "";
  const [searchTerm, setSearchTerm] = useState(initialQuery);
  const [results, setResults] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [activeFilter, setActiveFilter] = useState("all");
  const [fuse, setFuse] = useState(null);

  useEffect(() => {
    setSearchTerm(initialQuery);
  }, [initialQuery]);

  useEffect(() => {
    fetch("https://divisi-project.de/search.php")
      .then((response) => response.json())
      .then((data) => {
        const artistsComposers = Array.isArray(data.artists_composers) ? data.artists_composers : [];
        const recordings = Array.isArray(data.recordings) ? data.recordings.map((rec) => ({
          ...rec,
          artistNames: rec.artist_names ? rec.artist_names.split(', ') : [], // Konvertiere zu Array
          artistIds: rec.artist_ids ? rec.artist_ids.split(', ') : [], // Konvertiere zu Array
        })) : [];

        // Fuse.js initialisieren
        const fuseInstance = new Fuse(
          [
            ...artistsComposers.map((item) => ({
              ...item,
              category: item.type, // "artist" oder "composer"
            })),
            ...recordings.map((rec) => ({
              ...rec,
              category: "recording",
              name: rec.piece_name, // Für die Suche verwenden
            })),
          ],
          {
            keys: [
              {
                name: "name", // Suche im Namen
                weight: 0.7, // Höhere Gewichtung für den Namen
              },
              {
                name: "name", // Suche im Namen erneut
                getFn: (record) => record.name.split(" ").slice(1).join(" "), // Suche ab dem zweiten Wort
                weight: 0.3, // Geringere Gewichtung für Treffer ab dem zweiten Wort
              },
            ],
            threshold: 0.3, // Unscharfe Suche erlauben
            includeScore: true, // Relevanz-Score einbeziehen
            shouldSort: true, // Ergebnisse nach Relevanz sortieren
            location: 0, // Startposition der Suche
            distance: 100, // Maximale Distanz für unscharfe Treffer
          }
        );

        setFuse(fuseInstance);
      })
      .catch((error) => {
        console.error("Error initializing Fuse.js:", error);
      });
  }, []);

  useEffect(() => {
    if (fuse && initialQuery) {
      performSearch(initialQuery);
    }
  }, [fuse, initialQuery]);

  const performSearch = (query) => {
    if (!fuse) {
      console.warn("Fuse.js is not initialized yet.");
      return;
    }

    setIsLoading(true);

    // Fuse.js-Suche durchführen
    const fuseResults = fuse.search(query);

    // Ergebnisse nach Kategorien aufteilen
    const allResults = fuseResults.map((result) => result.item);

    setResults(allResults);
    setIsLoading(false);
  };

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    navigate(`/search?query=${encodeURIComponent(searchTerm)}`);
    performSearch(searchTerm);
  };

  const handleFilterClick = (filter) => {
    setActiveFilter(filter);
  };

  const filteredResults =
    activeFilter === "all"
      ? results
      : results.filter((result) => result.category === activeFilter);

  return (
    <div className="page-content">
      <h1>{t("searchpage")}</h1>
      <div className="search searchbar">
        <form onSubmit={handleSearchSubmit}>
          <input
            type="text"
            placeholder={t("search") + "..."}
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
          />
          <button type="submit">
            <img src={SearchIcon} alt={t("search")} />
          </button>
        </form>
      </div>

      {/* Filter buttons */}
      <div className="filters">
        <button
          className={activeFilter === "all" ? "active" : ""}
          onClick={() => handleFilterClick("all")}
        >
          {t("all")}
        </button>
        <button
          className={activeFilter === "artist" ? "active" : ""}
          onClick={() => handleFilterClick("artist")}
        >
          {t("artists")}
        </button>
        <button
          className={activeFilter === "composer" ? "active" : ""}
          onClick={() => handleFilterClick("composer")}
        >
          {t("composers")}
        </button>
        <button
          className={activeFilter === "recording" ? "active" : ""}
          onClick={() => handleFilterClick("recording")}
        >
          {t("recordings")}
        </button>
      </div>

      {/* Render Results */}
      {isLoading ? (
        <div>{t("loading")}</div>
      ) : (
        <div>
          {filteredResults.length > 0 ? (
            <ul className="search-results">
              {filteredResults.map((result) => {
                if (result.category === "artist" || result.category === "composer") {
                  return (
                    <li key={result.id}>
                      <ArtistComposerBlock
                        name={result.name}
                        category={result.category}
                        id={result.id}
                      />
                    </li>
                  );
                } else if (result.category === "recording") {
                  return (
                    <li key={result.id}>
                      <RecordingBlock
                        pieceName={result.piece_name}
                        catalogueNumber={result.catalogue_number}
                        composerName={result.composer_name}
                        composerId={result.composer_id}
                        recordingId={result.id}
                        artistNames={result.artistNames}
                        artistIds={result.artistIds}
                        img={result.img}
                      />
                    </li>
                  );
                }
                return null;
              })}
            </ul>
          ) : (
            <div>{t("no-results")}</div>
          )}
        </div>
      )}
    </div>
  );
}

export default SearchPage;