import React, { useState, useEffect, useRef } from 'react';
import '../App.css';
import ChevronDown from '../media/icons/chevron-down.svg';
import RecordingBlock from './recording-block'; // Import RecordingBlock component
import { useTranslation } from 'react-i18next';

const Recordings = () => {
    const [recordings, setRecordings] = useState([]);
    const [sortType, setSortType] = useState('');
    const [dropdownVisible, setDropdownVisible] = useState(false);
    const { t } = useTranslation();

    const dropdownRef = useRef(null); // Reference to the dropdown element

    // Fetch data when the component mounts
    useEffect(() => {
        fetch('https://divisi-project.de/getRecordings.php')
            .then(response => response.json())
            .then(data => {
                console.log('Fetched Data:', data);
                const formattedRecordings = data.map(recording => ({
                    id: recording.id,
                    recording_date: recording.recording_date,
                    img: recording.img,
                    piece: {
                        name: recording.piece?.name || 'Untitled Piece',
                        catalogue_number: recording.piece?.catalogue_number || ''
                    },
                    composer: {
                        id: recording.composer?.id || null,
                        name: recording.composer?.name || t('unknown-composer')
                    },
                    artists: Array.isArray(recording.artists) && recording.artists.length > 0 
                        ? recording.artists 
                        : [{ id: null, name: t('unknown-artist') }]
                }));
                setRecordings(formattedRecordings);
            })
            .catch(error => console.error('Error fetching recordings:', error));
    }, []);
    

    // Sort recordings whenever the sortType changes
    useEffect(() => {
        let sortedRecordings = [...recordings];
    
        if (sortType === "Name A - Z") {
            sortedRecordings.sort((a, b) => a.piece.name.localeCompare(b.piece.name));
        } else if (sortType === "Name Z - A") {
            sortedRecordings.sort((a, b) => b.piece.name.localeCompare(a.piece.name));
        } else if (sortType === "Date ASC") {
            sortedRecordings.sort((a, b) => new Date(a.recording_date || '1900-01-01') - new Date(b.recording_date || '1900-01-01'));
        } else if (sortType === "Date DESC") {
            sortedRecordings.sort((a, b) => new Date(b.recording_date || '1900-01-01') - new Date(a.recording_date || '1900-01-01'));
        }
    
        setRecordings(sortedRecordings);
    }, [sortType]); // Recalculate sorting when sortType changes

    // Handle clicks outside the dropdown to close it
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setDropdownVisible(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [dropdownRef]);

    const handleSortOptionClick = (option) => {
        setSortType(option);
        setDropdownVisible(false); // Hide dropdown after selecting an option
    };

    const toggleDropdown = () => {
        setDropdownVisible(!dropdownVisible);
    };

    // Determine the button text based on the selected sort option
    const getSortButtonText = () => {
        switch (sortType) {
            case "Name A - Z":
                return `${t('name')} A - Z`;
            case "Name Z - A":
                return `${t('name')} Z - A`;
            case "Date ASC":
                return `${t('date')} ASC`;
            case "Date DESC":
                return `${t('date')} DESC`;
            default:
                return t('sort-by');
        }
    };

    return (
        <div className="page-content">
            <h1>{t('recordings')}</h1>

            <div className="dropdown" ref={dropdownRef}>
                <button id="dropdownBtnSort" onClick={toggleDropdown}>
                    {getSortButtonText()} <img src={ChevronDown} alt="Sort" style={{ transform: dropdownVisible ? 'rotate(180deg)' : 'rotate(0deg)' }} />
                </button>
                {dropdownVisible && (
                    <div id="dropdownSortOptions" className="dropdownSortOptions">
                        <button className="sortOption" onClick={() => handleSortOptionClick("Name A - Z")}>
                            {t('name')} <span>A - Z</span>
                        </button>
                        <button className="sortOption" onClick={() => handleSortOptionClick("Name Z - A")}>
                            {t('name')} <span>Z - A</span>
                        </button>
                        <button className="sortOption" onClick={() => handleSortOptionClick("Date ASC")}>
                            {t('date')} <span>ASC</span>
                        </button>
                        <button className="sortOption" onClick={() => handleSortOptionClick("Date DESC")}>
                            {t('date')} <span>DESC</span>
                        </button>
                    </div>
                )}
            </div>

            {recordings.length === 0 ? (
                <p>{t('no-recordings')} </p>
            ) : (
                <ul className="recordings-list">
                    {recordings.map(recording => (
                        <li key={recording.id}>
                            {/* Use the RecordingBlock component to display each recording */}
                            <RecordingBlock
                                pieceName={recording.piece.name}
                                catalogueNumber={recording.piece.catalogue_number}
                                composerName={recording.composer.name}
                                composerId={recording.composer.id}
                                recordingId={recording.id}
                                artistNames={recording.artists.map(artist => artist.name || '')}
                                artistIds={recording.artists.map(artist => artist.id || null)}
                                img={recording.img}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
};

export default Recordings;