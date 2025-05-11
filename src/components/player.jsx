import React, { useState, useRef, useEffect } from "react";
import { useTranslation } from "react-i18next";

const MusicPlayer = ({ recordingData }) => {
  const [audioContext, setAudioContext] = useState(null);
  const [gainNodes, setGainNodes] = useState([]);
  const [isPlaying, setIsPlaying] = useState(false);
  const audioRef = useRef(null);
  const [isInitialized, setIsInitialized] = useState(false);
  const [currentTime, setCurrentTime] = useState(0);
  const [duration, setDuration] = useState(0);
  const [expandedGroups, setExpandedGroups] = useState({});
  const [showVolumeControl, setShowVolumeControl] = useState({});
  const [groupVolume, setGroupVolume] = useState({});
  const [instrumentVolume, setInstrumentVolume] = useState({});
  const { t } = useTranslation();

  // Debug logging
  console.log('MusicPlayer Data:', {
    recording: recordingData?.recording,
    instruments: recordingData?.instruments,
    audioUrl: recordingData?.recording?.complete_recording
  });

  const getAudioUrl = (baseUrl) => {
    const preferredBitrate = localStorage.getItem('preferredBitrate') || 'medium';
    const url = baseUrl.replace('.flac', ''); // Remove .flac extension if present
    
    switch (preferredBitrate) {
      case 'low':
        return `${url}-192kbps.opus`;
      case 'medium':
        return `${url}-320kbps.opus`;
      case 'lossless':
      default:
        return `${url}.flac`;
    }
  };

  useEffect(() => {
    if (recordingData?.recording?.complete_recording && !isInitialized) {
      const audio = new Audio();
      
      // Log the selected audio file URL
      const audioUrl = getAudioUrl(recordingData.recording.complete_recording);
      const fullUrl = `https://divisi.nbg1.your-objectstorage.com/${audioUrl}`;
      
      // Add event listeners for time tracking
      audio.addEventListener('loadedmetadata', () => {
        console.log('Metadata loaded:', {
          duration: audio.duration,
          readyState: audio.readyState
        });
        setDuration(audio.duration);
      });

      audio.addEventListener('timeupdate', () => {
        setCurrentTime(audio.currentTime);
      });

      audio.addEventListener('loadstart', () => {
        console.log('Audio loading started');
      });

      audio.addEventListener('canplay', () => {
        console.log('Audio can play:', {
          duration: audio.duration,
          currentTime: audio.currentTime
        });
        // Set initial duration when audio is ready
        setDuration(audio.duration);
      });

      audio.addEventListener('error', (e) => {
        console.error('Audio loading error:', {
          error: e,
          code: audio.error?.code,
          message: audio.error?.message,
          url: audio.src,
          networkState: audio.networkState,
          readyState: audio.readyState
        });
      });

      // Test URL and set up audio
      fetch(fullUrl, { method: 'HEAD' })
        .then(response => {
          console.log('URL check result:', {
            ok: response.ok,
            status: response.status,
            statusText: response.statusText
          });
          if (response.ok) {
            audio.src = fullUrl;
            audio.crossOrigin = "anonymous";
            audio.preload = "metadata"; // Ensure metadata is loaded
            audio.load();
          }
        })
        .catch(error => {
          console.error('URL check failed:', error);
        });
      
      audioRef.current = audio;
      setIsInitialized(true);

      // Cleanup function
      return () => {
        audio.removeEventListener('loadedmetadata', () => {});
        audio.removeEventListener('timeupdate', () => {});
        audio.removeEventListener('loadstart', () => {});
        audio.removeEventListener('canplay', () => {});
      };
    }
  }, [recordingData, isInitialized]);

  const initAudioContext = () => {
    if (!audioRef.current) {
      console.log('No audio element available');
      return null;
    }
  
    try {
      if (!audioContext) {
        const newAudioContext = new (window.AudioContext || window.webkitAudioContext)();
        const source = newAudioContext.createMediaElementSource(audioRef.current);
  
        // Create stereo mix gain node
        const stereoGain = newAudioContext.createGain();
        stereoGain.gain.value = 1.0;
  
        // Get all instruments based on data structure
        let allInstruments = [];
  
        // Get instruments from the appropriate source
        const grouping = getGroupedInstruments();
        if (grouping) {
          // Flatten all instruments from groups
          Object.values(grouping.groups).forEach(instruments => {
            if (Array.isArray(instruments)) {
              allInstruments = [...allInstruments, ...instruments];
            }
          });
        }
  
        console.log('Processing instruments:', {
          groupingType: grouping?.type,
          instrumentCount: allInstruments.length,
          instruments: allInstruments
        });
  
        if (allInstruments.length === 0) {
          console.error('No instruments found in the data');
          return null;
        }
  
        // Rest of the initialization code
        allInstruments.sort((a, b) => a.channel - b.channel);
        const maxChannel = Math.max(...allInstruments.map(i => i.channel || 0), 1);
        const numChannels = Math.max(2, maxChannel);
  
        const splitter = newAudioContext.createChannelSplitter(numChannels);
        const merger = newAudioContext.createChannelMerger(2);
  
        source.connect(splitter);
        source.connect(stereoGain);
        stereoGain.connect(newAudioContext.destination);
  
        const newGainNodes = allInstruments.map(instrument => {
          const gainNode = newAudioContext.createGain();
          gainNode.gain.value = 0.0;
  
          const channelIndex = (instrument.channel || 1) - 1;
          splitter.connect(gainNode, channelIndex);
          gainNode.connect(merger, 0, 0);
          gainNode.connect(merger, 0, 1);
  
          return {
            node: gainNode,
            instrumentId: instrument.id,
            channel: instrument.channel,
            name: instrument.name
          };
        });
  
        merger.connect(newAudioContext.destination);
        setGainNodes(newGainNodes);
        setAudioContext(newAudioContext);
        return newAudioContext;
      }
      return audioContext;
    } catch (error) {
      console.error('Audio initialization error:', error);
      return null;
    }
  };

  const handlePlay = async () => {
    if (!audioRef.current) {
      console.error('No audio element available');
      return;
    }

    try {
      const ctx = initAudioContext();
      if (!ctx) {
        console.error('Failed to initialize audio context');
        return;
      }

      if (ctx.state === 'suspended') {
        await ctx.resume();
      }

      // Resume playback without resetting the current time
      if (audioRef.current.paused) {
        await audioRef.current.play();
        setIsPlaying(true);
      }
    } catch (error) {
      console.error('Error playing audio:', error);
    }
  };

  const handlePause = () => {
    if (!audioRef.current) return;
    audioRef.current.pause();
    setIsPlaying(false);
  };

  const handleSeek = (e) => {
    if (!audioRef.current) return;
    
    const time = parseFloat(e.target.value);
    console.log('Seeking to:', {
      requestedTime: time,
      currentTime: audioRef.current.currentTime,
      duration: audioRef.current.duration
    });
  
    if (isFinite(time) && time >= 0 && time <= audioRef.current.duration) {
      audioRef.current.currentTime = time;
      setCurrentTime(time);
    }
  };

  const toggleGroup = (groupName) => {
    setExpandedGroups(prev => ({
      ...prev,
      [groupName]: !prev[groupName]
    }));
  };

  const toggleVolumeControl = (id) => {
    setShowVolumeControl(prev => ({
      ...prev,
      [id]: !prev[id]
    }));
  };

  const handleGroupVolume = (groupName, value) => {
    const instruments = recordingData.instruments.custom_groups[groupName];
    const normalizedValue = parseFloat(value); // Use the exact multiplier (e.g., 3x, 4x, etc.)
    instruments.forEach(instrument => {
      const gainNode = gainNodes.find(gn => gn.instrumentId === instrument.id);
      if (gainNode) {
        gainNode.node.gain.value = normalizedValue;
      }
    });
  };

  const handleGroupClick = (groupName) => {
    const value = groupVolume[groupName] === "4" ? "1" : "4"; // Toggle between 1x and 4x
    setGroupVolume(prev => ({...prev, [groupName]: value}));
    handleGroupVolume(groupName, value);
  };

  const handleInstrumentClick = (instrumentId) => {
    const currentMultiplier = parseFloat(instrumentVolume[`inst-${instrumentId}`] || 1);
    const newMultiplier = currentMultiplier === 4 ? 1 : 4; // Toggle between 1x and 4x
    setInstrumentVolume(prev => ({ ...prev, [`inst-${instrumentId}`]: newMultiplier }));
    const gainNode = gainNodes.find(gn => gn.instrumentId === instrumentId);
    if (gainNode) {
      gainNode.node.gain.value = newMultiplier;
    }
  };

  const getGroupedInstruments = () => {
    if (!recordingData?.instruments) {
      console.log('No instruments data');
      return null;
    }
  
    // Case 1: Custom recording groups take precedence if they exist
    if (recordingData.instruments.custom_groups && 
        Object.keys(recordingData.instruments.custom_groups).length > 0) {
      return {
        type: 'custom',
        groups: recordingData.instruments.custom_groups
      };
    }
  
    // Case 2: No grouping (use_instrument_groups = false)
    if (!recordingData.instruments.use_instrument_groups) {
      return {
        type: 'flat',
        groups: { 'All Instruments': recordingData.instruments.all_instruments }
      };
    }
  
    // Case 3: Standard instrument groups
    if (recordingData.instruments.standard_groups && 
        Object.keys(recordingData.instruments.standard_groups).length > 0) {
      return {
        type: 'standard',
        groups: recordingData.instruments.standard_groups
      };
    }
  
    return null;
  };

  // Cleanup function
  useEffect(() => {
    return () => {
      if (audioRef.current) {
        audioRef.current.pause();
        audioRef.current.remove();
      }
      if (audioContext) {
        audioContext.close();
      }
    };
  }, [audioContext]);

  return (
    <div className="music-player">
      <div className="player-controls">
        <button 
          onClick={isPlaying ? handlePause : handlePlay}
          className={`play-button ${isPlaying ? 'playing' : ''}`}
        >
          {isPlaying ? 'Pause' : 'Play'}
        </button>
        
        {/* Seekbar */}
        <div className="seek-control">
          <input
            type="range"
            min="0"
            max={duration || 0}
            value={currentTime}
            onChange={handleSeek}
            className="seek-bar"
          />
          <div className="time-display">
            {Math.floor(currentTime / 60)}:{Math.floor(currentTime % 60).toString().padStart(2, '0')}
            {' / '}
            {Math.floor(duration / 60)}:{Math.floor(duration % 60).toString().padStart(2, '0')}
          </div>
        </div>
      </div>

      <div className="mixer-controls">
        <h2>{t('voices')}</h2>
        {(() => {
          const grouping = getGroupedInstruments();
          if (!grouping) return null;
      
          return Object.entries(grouping.groups).map(([groupName, instruments]) => (
            <div key={groupName} className="instrument-group">
              {/* Only show group header if not using flat listing */}
              {grouping.type !== 'flat' && (
                <div className="group-header">
                  <button 
                    className="chevron-button"
                    onClick={() => toggleGroup(groupName)}
                  >
                    {expandedGroups[groupName] ? '▼' : '▶'}
                  </button>
                  <h3 
                    onClick={() => handleGroupClick(groupName)}
                    className={`group-name ${groupVolume[groupName] === "4" ? 'highlighted' : ''}`}
                    style={{ cursor: 'pointer' }}
                  >
                    {groupName}
                  </h3>
                  <button 
                    className="volume-menu-button"
                    onClick={() => toggleVolumeControl(groupName)}
                  >
                    ⋮
                  </button>
                </div>
              )}
              
              {/* Show volume control for group */}
              {showVolumeControl[groupName] && (
                <div className="volume-control">
                  <input
                    type="range"
                    min="3"
                    max="10"
                    step="0.5"
                    value={groupVolume[groupName] || 4} // Default to 4x
                    onChange={(e) => {
                      const value = e.target.value;
                      setGroupVolume(prev => ({ ...prev, [groupName]: value }));
                      handleGroupVolume(groupName, value);
                    }}
                  />
                  <span>{parseFloat(groupVolume[groupName] || 4).toFixed(1)}x</span>
                </div>
              )}
      
              {/* Show instruments (always for flat listing, or when group is expanded) */}
              {(grouping.type === 'flat' || expandedGroups[groupName]) && (
                <div className="instruments-list">
                  {instruments.map(instrument => (
                    <div key={instrument.id} className="instrument-control">
                      <div 
                        className="instrument-header"
                        onClick={() => handleInstrumentClick(instrument.id)}
                        style={{ cursor: 'pointer' }}
                      >
                        <span className={instrumentVolume[`inst-${instrument.id}`] === "300" ? 'highlighted' : ''}>
                          {instrument.name}
                        </span>
                        <button 
                          className="volume-menu-button"
                          onClick={(e) => {
                            e.stopPropagation(); // Prevent triggering parent click
                            toggleVolumeControl(`inst-${instrument.id}`);
                          }}
                        >
                          ⋮
                        </button>
                      </div>
                      
                      {/* Volume control for instruments */}
                      {showVolumeControl[`inst-${instrument.id}`] && (
                        <div className="volume-control">
                          <input
                            type="range"
                            min="3"
                            max="10"
                            step="0.5"
                            value={instrumentVolume[`inst-${instrument.id}`] || 4} // Default to 4x
                            onChange={(e) => {
                              const value = e.target.value;
                              setInstrumentVolume(prev => ({ ...prev, [`inst-${instrument.id}`]: value }));
                              const gainNode = gainNodes.find(gn => gn.instrumentId === instrument.id);
                              if (gainNode) {
                                gainNode.node.gain.value = parseFloat(value);
                              }
                            }}
                          />
                          <span>{parseFloat(instrumentVolume[`inst-${instrument.id}`] || 4).toFixed(1)}x</span>
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          ));
        })()}
      </div>
    </div>
  );
};

export default MusicPlayer;