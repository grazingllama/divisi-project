import React from 'react';

import HomeIcon from '../media/icons/home.svg';
import ChevronDown from '../media/icons/chevron-down.svg';
import SettingsIcon from '../media/icons/settings.svg';

const icons = {
  home: HomeIcon,
  chevrondown: ChevronDown,
  settings: SettingsIcon,
  // Add more icons as needed
};

const Icon = ({ name, ...props }) => {
  const SvgIcon = icons[name];
  return SvgIcon ? <SvgIcon {...props} /> : null;
};

export default Icon;
