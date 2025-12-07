<style>
    /* Hide sidebar navigation completely */
    .fi-sidebar,
    [data-sidebar],
    aside[class*="sidebar"],
    nav[class*="sidebar"] {
        display: none !important;
    }
    
    /* Hide top navigation/header */
    .fi-topbar,
    [data-topbar],
    header[class*="topbar"],
    header[class*="header"] {
        display: none !important;
    }
    
    /* Make main content full width */
    .fi-main,
    [data-main],
    main[class*="main"] {
        margin-left: 0 !important;
        padding-left: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    
    /* Hide account widget and other header widgets */
    .fi-account-widget,
    .fi-user-menu,
    [data-user-menu] {
        display: none !important;
    }
    
    /* Remove padding/margins from page container */
    .fi-page,
    [data-page] {
        padding: 0.5rem !important;
        margin: 0 !important;
    }
    
    /* Full width for resource pages */
    .fi-resource,
    [data-resource] {
        padding: 0.5rem !important;
    }
    
    /* Hide breadcrumbs if present */
    .fi-breadcrumbs,
    [data-breadcrumbs],
    nav[aria-label="breadcrumb"] {
        display: none !important;
    }
    
    /* Ensure body has no extra padding */
    body {
        padding: 0 !important;
        margin: 0 !important;
    }
    
    /* Hide any footer elements */
    footer {
        display: none !important;
    }
    
    /* Hide tenant switcher if present */
    .fi-tenant-menu,
    [data-tenant-menu] {
        display: none !important;
    }
</style>


