/** PUBLIC values only. Never put an OAuth client secret, refresh token or API key here. */
window.AADITECHS_PUBLIC_CONFIG = Object.freeze({
  // Link supplied by the business owner. Verify that it resolves to the correct profile.
  googleBusinessProfileUrl: 'https://share.google/Zn5RDeewInDijwD3i',
  // Replace with the exact "Get more reviews" URL from the verified Business Profile.
  googleReviewRequestUrl: '',
  features: Object.freeze({
    googleReviewsApi: false, // Enable only after API access and usage approval.
    analytics: false, // Optional consent-based website analytics; keep OFF until acceptance.
    leadSubmission: false, // Enable only after DB schema, privacy notice, SMTP (optional), security and staging validation.
  }),
  googleReviewsEndpoint: '/api/google-reviews.php',
  leadEndpoint: '/api/lead.php',
});
