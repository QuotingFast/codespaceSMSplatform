// Ringba Integration for QuotingFast.io
// Current Date: 2025-06-19

// Ringba "pool" scripts
const INSURED_NO_DUI_SCRIPT   = "//b-js.ringba.com/CA134a8682c9e84d97a6fea1a0d2d4361f";
const INSURED_DUI_SCRIPT      = "//b-js.ringba.com/CAa0c48cb25286491b9e47f8b5afd6fbc7";
const UNINSURED_NO_DUI_SCRIPT = "//b-js.ringba.com/CAa0c48cb25286491b9e47f8b5afd6fbc7";
const UNINSURED_DUI_SCRIPT    = "//b-js.ringba.com/CAa0c48cb25286491b9e47f8b5afd6fbc7";
const ALLSTATE_SCRIPT         = "//b-js.ringba.com/CA5e3e25cc73184c00966cd53dc678fa72";

// Corresponding phone numbers (DIDs)
const INSURED_NO_DUI_DID   = "+18889711908";
const INSURED_DUI_DID      = "+18336503121";
const UNINSURED_NO_DUI_DID = "+18336503121";
const UNINSURED_DUI_DID    = "+18336503121";
const ALLSTATE_DID         = "+18336274480";

// Initialize Ringba tracking tags
window._rgba_tags = window._rgba_tags || [];

/**
 * Choose the right Ringba script and phone number based on user qualification
 */
function finalizeRingbaIntegration() {
    let ringbaScript = '';
    let phoneNumber = '';
    
    // Determine which script and DID to use based on user status
    if (typeof userProviderStatus !== 'undefined' && userProviderStatus === "allstate") {
        ringbaScript = ALLSTATE_SCRIPT;
        phoneNumber = ALLSTATE_DID;
    }
    else if (typeof userDUIStatus !== 'undefined' && userDUIStatus === "yes") {
        ringbaScript = UNINSURED_DUI_SCRIPT;
        phoneNumber = UNINSURED_DUI_DID;
    }
    else if (typeof userInsuranceStatus !== 'undefined' && userInsuranceStatus === "yes") {
        ringbaScript = INSURED_NO_DUI_SCRIPT;
        phoneNumber = INSURED_NO_DUI_DID;
    }
    else {
        ringbaScript = UNINSURED_NO_DUI_SCRIPT;
        phoneNumber = UNINSURED_NO_DUI_DID;
    }
    
    // Load the correct script and update phone numbers
    loadRingbaScript(ringbaScript);
    updatePhoneNumbers(phoneNumber);
}

/**
 * Load the Ringba script with tracking parameters
 */
function loadRingbaScript(url) {
    // Get URL parameters (UTM, FBCLID, etc.)
    const urlParams = new URLSearchParams(window.location.search);
    const fbclid = urlParams.get('fbclid') || '';
    const utm_source = urlParams.get('utm_source') || '';
    const utm_medium = urlParams.get('utm_medium') || '';
    const utm_campaign = urlParams.get('utm_campaign') || '';
    const utm_content = urlParams.get('utm_content') || '';
    const utm_term = urlParams.get('utm_term') || '';
    
    // Get insurance and DUI status (defaulting to empty string if undefined)
    const state = typeof userState !== 'undefined' ? userState : '';
    const insurance = typeof userInsuranceStatus !== 'undefined' ? userInsuranceStatus : '';
    const dui = typeof userDUIStatus !== 'undefined' ? userDUIStatus : '';
    const persuasion_attempts = '1'; // Default value
    
    // Build query string
    let queryString = '';
    if (fbclid) queryString += `&fbclid=${encodeURIComponent(fbclid)}`;
    if (utm_source) queryString += `&utm_source=${encodeURIComponent(utm_source)}`;
    if (utm_medium) queryString += `&utm_medium=${encodeURIComponent(utm_medium)}`;
    if (utm_campaign) queryString += `&utm_campaign=${encodeURIComponent(utm_campaign)}`;
    if (utm_content) queryString += `&utm_content=${encodeURIComponent(utm_content)}`;
    if (utm_term) queryString += `&utm_term=${encodeURIComponent(utm_term)}`;
    if (state) queryString += `&state=${encodeURIComponent(state)}`;
    if (insurance) queryString += `&insurance=${encodeURIComponent(insurance)}`;
    if (dui) queryString += `&dui=${encodeURIComponent(dui)}`;
    queryString += `&persuasion_attempts=${encodeURIComponent(persuasion_attempts)}`;
    
    // Remove the first "&" if query string is not empty
    if (queryString.length > 0) {
        queryString = '?' + queryString.substring(1);
    }
    
    // Create and inject script tag
    const script = document.createElement('script');
    script.src = url + queryString;
    script.async = true;
    document.body.appendChild(script);
}

/**
 * Update all phone number links and attach tracking events
 */
function updatePhoneNumbers(number) {
    // Update all phone links
    const phoneLinks = document.querySelectorAll('.call-link');
    phoneLinks.forEach(link => {
        if (link.tagName === 'A') {
            link.href = `tel:${number}`;
        }
    });
    
    // Specifically update the main call button if exists
    const mainCallBtn = document.getElementById('main-call-btn');
    if (mainCallBtn) {
        mainCallBtn.addEventListener('click', function(e) {
            // Track with Facebook Pixel if available
            if (typeof fbq === 'function') {
                fbq('track', 'InsuredCallClick', {
                    insurance: typeof userInsuranceStatus !== 'undefined' ? userInsuranceStatus : '',
                    dui: typeof userDUIStatus !== 'undefined' ? userDUIStatus : '',
                    provider: typeof userProviderStatus !== 'undefined' ? userProviderStatus : '',
                    state: typeof userState !== 'undefined' ? userState : ''
                });
            }
            
            // Push CallInitiated event to Ringba tags
            window._rgba_tags.push({
                type: "CallInitiated",
                phone_number: number,
                insurance: typeof userInsuranceStatus !== 'undefined' ? userInsuranceStatus : '',
                dui: typeof userDUIStatus !== 'undefined' ? userDUIStatus : '',
                provider: typeof userProviderStatus !== 'undefined' ? userProviderStatus : '',
                state: typeof userState !== 'undefined' ? userState : ''
            });
        });
    }
}

// Auto-initialize on standard call links without requiring the tracking function
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on homepage (no user variables)
    if (typeof userInsuranceStatus === 'undefined') {
        // On homepage, use default number (INSURED_NO_DUI_DID)
        updatePhoneNumbers(INSURED_NO_DUI_DID);
    }
});