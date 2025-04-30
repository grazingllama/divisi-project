import React, { useState, useEffect } from "react";
import { BrowserRouter as Router, Route, Routes, useLocation } from "react-router-dom";
import { useTranslation } from 'react-i18next';
import './i18n';

// Component Imports
import Header from "./components/header";
import Sidebar from "./components/sidebar";
import Profile from "./components/profile";
import Settings from "./components/settings";
import Home from "./components/home";
import Recordings from "./components/recordings";
import About from "./components/about";
import MobileFooter from "./components/mobile-footer";
import ArtistPage from "./components/artist";
import ComposerPage from "./components/composer";
import SearchPage from "./components/search";
import MobileHeader from "./components/header-mobile";
import RecordingPage from "./components/recording";

// Custom Hook for Viewport Width
export const useViewport = () => {
  const [width, setWidth] = React.useState(window.innerWidth);

  useEffect(() => {
    const handleWindowResize = () => setWidth(window.innerWidth);
    window.addEventListener("resize", handleWindowResize);
    return () => window.removeEventListener("resize", handleWindowResize);
  }, []);

  return { width };
};

// Main Navigation Component
const MainNavigation = ({ activePage, setActivePage, setContent }) => {
  const { width } = useViewport();
  const breakpoint = 620;
  const { t } = useTranslation();

  return (
    <>
      {width < breakpoint ? (
        <div className="footer">
          <div className="footer-gradient"></div>
          <MobileFooter
            activePage={activePage}
            setActivePage={setActivePage}
            setContent={setContent}
            t={t}
          />
        </div>
      ) : (
        <Sidebar
          activePage={activePage}
          setActivePage={setActivePage}
          setContent={setContent}
          t={t}
        />
      )}
    </>
  );
};

// Secondary Navigation Component
const SecNavigation = ({ activePage, setActivePage }) => {
  const { width } = useViewport();
  const breakpoint = 620;
  const { t } = useTranslation();

  return (
    <>
      {width < breakpoint ? (
        <MobileHeader
          activePage={activePage}
          setActivePage={setActivePage}
          t={t}
        />
      ) : (
        <Header
          activePage={activePage}
          setActivePage={setActivePage}
          t={t}
        />
      )}
    </>
  );
};

// App Content Component (Handles Routes)
const AppContent = ({ activePage, setActivePage, content, setContent }) => {
  const location = useLocation(); // Track current location
  const { t } = useTranslation();

  // Update activePage based on the current route
  useEffect(() => {
    const pathToPage = {
      "/": "home",
      "/profile": "profile",
      "/settings": "settings",
      "/recordings": "recordings",
      "/about": "about",
      "/search": "searchpage",
    };
    setActivePage(pathToPage[location.pathname] || "home");
    }, [location.pathname, setActivePage]);

  return (
    <div className="content">
      <Routes>
        <Route path="/" element={<Home t={t} />} />
        <Route path="/artist/:id" element={<ArtistPage t={t} />} />
        <Route path="/composer/:id" element={<ComposerPage t={t} />} />
        <Route path="/profile" element={<Profile t={t} />} />
        <Route path="/settings" element={<Settings t={t} />} />
        <Route path="/recordings" element={<Recordings t={t} />} />
        <Route path="/about" element={<About t={t} />} />
        <Route path="/search" element={<SearchPage t={t} />} />
        <Route path="/recording/:id" element={<RecordingPage t={t} />} />
      </Routes>

    </div>
  );
};

// Main App Component
function App() {
  const [activePage, setActivePage] = useState("home");
  const [content, setContent] = useState(<Home />);
  const { t } = useTranslation();

  return (
    <Router basename="">
      <div className="app-container">
        {/* Secondary Navigation */}
        <SecNavigation
          activePage={activePage}
          setActivePage={setActivePage}
          t={t}
        />

        {/* Main Navigation */}
        <MainNavigation
          activePage={activePage}
          setActivePage={setActivePage}
          setContent={setContent}
          t={t}
        />

        {/* App Content */}
        <AppContent
          activePage={activePage}
          setActivePage={setActivePage}
          content={content}
          setContent={setContent}
        />
      </div>
    </Router>
  );
}

export default App;