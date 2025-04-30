import React from "react";
import DOMPurify from "dompurify";

const FormattedText = ({ htmlContent }) => {
  // Sanitize the HTML content
  const sanitizedHTML = DOMPurify.sanitize(htmlContent);

  return (
    <span
      dangerouslySetInnerHTML={{ __html: sanitizedHTML }}
    />
  );
};

export default FormattedText;