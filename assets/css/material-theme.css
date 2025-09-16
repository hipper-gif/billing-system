/* 
 * material-theme.css - Smiley配食事業システム
 * マテリアルデザインテーマ統一ファイル
 * 最終更新: 2025年9月16日
 */

/* ===== CSS変数定義 ===== */
:root {
    /* Primary Colors */
    --primary-blue: #2196F3;
    --primary-green: #4CAF50;
    --primary-dark: #1976D2;
    
    /* Status Colors */
    --success-green: #4CAF50;
    --warning-amber: #FFC107;
    --error-red: #F44336;
    --info-blue: #2196F3;
    
    /* Surface Colors */
    --surface-white: #FFFFFF;
    --surface-grey: #F5F5F5;
    --surface-dark: #424242;
    
    /* Text Colors */
    --text-dark: #212121;
    --text-secondary: #757575;
    --text-light: #FFFFFF;
    
    /* Border & Divider */
    --divider-grey: #E0E0E0;
    --border-light: #EEEEEE;
    
    /* Spacing */
    --spacing-xs: 4px;
    --spacing-sm: 8px;
    --spacing-md: 16px;
    --spacing-lg: 24px;
    --spacing-xl: 32px;
    --spacing-xxl: 48px;
    
    /* Border Radius */
    --radius-small: 4px;
    --radius-normal: 8px;
    --radius-large: 12px;
    --radius-xl: 16px;
    --radius-round: 50%;
    
    /* Elevation (Box Shadows) */
    --elevation-0: none;
    --elevation-1: 0 2px 4px rgba(0,0,0,0.1);
    --elevation-2: 0 4px 8px rgba(0,0,0,0.12);
    --elevation-3: 0 8px 16px rgba(0,0,0,0.15);
    --elevation-4: 0 12px 24px rgba(0,0,0,0.18);
    
    /* Transitions */
    --transition-fast: 0.15s ease;
    --transition-normal: 0.3s ease;
    --transition-slow: 0.5s ease;
    
    /* Typography */
    --font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    --font-weight-light: 300;
    --font-weight-normal: 400;
    --font-weight-medium: 500;
    --font-weight-bold: 700;
    
    /* Font Sizes */
    --font-xs: 0.75rem;
    --font-sm: 0.875rem;
    --font-base: 1rem;
    --font-lg: 1.125rem;
    --font-xl: 1.25rem;
    --font-2xl: 1.5rem;
    --font-3xl: 2rem;
    --font-4xl: 2.5rem;
}

/* ===== Base Styles ===== */
* {
    box-sizing: border-box;
}

body {
    font-family: var(--font-family);
    font-weight: var(--font-weight-normal);
    line-height: 1.6;
    color: var(--text-dark);
    background-color: var(--surface-grey);
    margin: 0;
    padding: 0;
}

/* ===== Material Design Components ===== */

/* Cards */
.material-card {
    background: var(--surface-white);
    border-radius: var(--radius-normal);
    box-shadow: var(--elevation-1);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-md);
    transition: box-shadow var(--transition-normal);
}

.material-card:hover {
    box-shadow: var(--elevation-2);
}

.material-card .card-header {
    border-bottom: 1px solid var(--divider-grey);
    padding-bottom: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}

.material-card .card-title {
    font-size: var(--font-xl);
    font-weight: var(--font-weight-medium);
    margin: 0;
    color: var(--text-dark);
}

/* Buttons */
.btn-material {
    font-family: var(--font-family);
    font-weight: var(--font-weight-medium);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    border-radius: var(--radius-small);
    padding: 12px 24px;
    transition: all var(--transition-normal);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: var(--font-sm);
}

.btn-material:hover {
    transform: translateY(-2px);
    box-shadow: var(--elevation-2);
    text-decoration: none;
}

.btn-material:active {
    transform: translateY(0);
    box-shadow: var(--elevation-1);
}

/* Button Sizes */
.btn-material-small {
    padding: 8px 16px;
    font-size: var(--font-xs);
}

.btn-material-large {
    padding: 16px 32px;
    font-size: var(--font-lg);
    min-height: 56px;
}

/* Button Variants */
.btn-material.btn-primary {
    background: var(--primary-blue);
    color: var(--text-light);
}

.btn-material.btn-success {
    background: var(--success-green);
    color: var(--text-light);
}

.btn-material.btn-warning {
    background: var(--warning-amber);
    color: var(--text-dark);
}

.btn-material.btn-danger {
    background: var(--error-red);
    color: var(--text-light);
}

.btn-material.btn-info {
    background: var(--info-blue);
    color: var(--text-light);
}

.btn-material.btn-flat {
    background: transparent;
    color: var(--primary-blue);
    box-shadow: none;
}

.btn-material.btn-outline {
    background: transparent;
    border: 2px solid var(--primary-blue);
    color: var(--primary-blue);
    box-shadow: none;
}

/* Forms */
.material-input {
    width: 100%;
    padding: 16px 12px 8px;
    border: none;
    border-bottom: 2px solid var(--divider-grey);
    background: transparent;
    font-size: var(--font-base);
    font-family: var(--font-family);
    color: var(--text-dark);
    transition: border-color var(--transition-normal);
}

.material-input:focus {
    outline: none;
    border-bottom-color: var(--primary-blue);
}

.material-input-group {
    position: relative;
    margin-bottom: var(--spacing-lg);
}

.material-input-label {
    position: absolute;
    left: 12px;
    top: 16px;
    color: var(--text-secondary);
    font-size: var(--font-sm);
    pointer-events: none;
    transition: all var(--transition-normal);
}

.material-input:focus + .material-input-label,
.material-input:not(:placeholder-shown) + .material-input-label {
    top: 4px;
    font-size: var(--font-xs);
    color: var(--primary-blue);
}

/* Alerts */
.material-alert {
    display: flex;
    align-items: flex-start;
    padding: var(--spacing-md);
    border-radius: var(--radius-small);
    margin-bottom: var(--spacing-md);
    font-size: var(--font-sm);
}

.material-alert .alert-icon {
    margin-right: var(--spacing-md);
    font-size: var(--font-lg);
    flex-shrink: 0;
}

.material-alert.alert-success {
    background-color: #E8F5E8;
    color: #2E7D32;
    border-left: 4px solid var(--success-green);
}

.material-alert.alert-warning {
    background-color: #FFF8E1;
    color: #F57C00;
    border-left: 4px solid var(--warning-amber);
}

.material-alert.alert-error {
    background-color: #FFEBEE;
    color: #C62828;
    border-left: 4px solid var(--error-red);
}

.material-alert.alert-info {
    background-color: #E3F2FD;
    color: #1565C0;
    border-left: 4px solid var(--info-blue);
}

/* Floating Action Button */
.fab {
    position: fixed;
    bottom: var(--spacing-lg);
    right: var(--spacing-lg);
    width: 56px;
    height: 56px;
    border-radius: var(--radius-round);
    background: var(--primary-blue);
    color: var(--text-light);
    border: none;
    box-shadow: var(--elevation-3);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--font-xl);
    transition: all var(--transition-normal);
    z-index: 1000;
}

.fab:hover {
    transform: scale(1.1);
    box-shadow: var(--elevation-4);
}

.fab:active {
    transform: scale(0.95);
}

/* Navigation */
.material-nav {
    background: var(--surface-white);
    box-shadow: var(--elevation-1);
    padding: var(--spacing-md) 0;
}

/* Tables */
.material-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--surface-white);
    border-radius: var(--radius-normal);
    overflow: hidden;
    box-shadow: var(--elevation-1);
}

.material-table th,
.material-table td {
    padding: var(--spacing-md);
    text-align: left;
    border-bottom: 1px solid var(--divider-grey);
}

.material-table th {
    background: var(--surface-grey);
    font-weight: var(--font-weight-medium);
    color: var(--text-dark);
    font-size: var(--font-sm);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.material-table tbody tr:hover {
    background-color: rgba(33, 150, 243, 0.04);
}

/* Progress & Loading */
.material-progress {
    height: 4px;
    background: var(--divider-grey);
    border-radius: var(--radius-small);
    overflow: hidden;
}

.material-progress-bar {
    height: 100%;
    background: var(--primary-blue);
    border-radius: var(--radius-small);
    transition: width var(--transition-normal);
}

/* Chips & Tags */
.material-chip {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 16px;
    background: var(--surface-grey);
    color: var(--text-dark);
    font-size: var(--font-xs);
    font-weight: var(--font-weight-medium);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.material-chip.chip-success {
    background: var(--success-green);
    color: var(--text-light);
}

.material-chip.chip-warning {
    background: var(--warning-amber);
    color: var(--text-dark);
}

.material-chip.chip-error {
    background: var(--error-red);
    color: var(--text-light);
}

/* Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeIn 0.5s ease-out;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.animate-slide-in-right {
    animation: slideInRight 0.6s ease-out;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.animate-pulse {
    animation: pulse 2s infinite;
}

/* Utility Classes */
.text-small {
    font-size: var(--font-sm);
}

.text-large {
    font-size: var(--font-lg);
}

.text-xs {
    font-size: var(--font-xs);
}

.font-medium {
    font-weight: var(--font-weight-medium);
}

.font-bold {
    font-weight: var(--font-weight-bold);
}

.color-primary {
    color: var(--primary-blue);
}

.color-success {
    color: var(--success-green);
}

.color-warning {
    color: var(--warning-amber);
}

.color-error {
    color: var(--error-red);
}

.color-secondary {
    color: var(--text-secondary);
}

.bg-primary {
    background-color: var(--primary-blue);
}

.bg-success {
    background-color: var(--success-green);
}

.bg-warning {
    background-color: var(--warning-amber);
}

.bg-error {
    background-color: var(--error-red);
}

.rounded {
    border-radius: var(--radius-normal);
}

.rounded-small {
    border-radius: var(--radius-small);
}

.rounded-large {
    border-radius: var(--radius-large);
}

.shadow-1 {
    box-shadow: var(--elevation-1);
}

.shadow-2 {
    box-shadow: var(--elevation-2);
}

.shadow-3 {
    box-shadow: var(--elevation-3);
}

/* Responsive Design */
@media (max-width: 768px) {
    .fab {
        bottom: var(--spacing-md);
        right: var(--spacing-md);
        width: 48px;
        height: 48px;
    }
    
    .btn-material-large {
        padding: 12px 24px;
        font-size: var(--font-base);
        min-height: 48px;
    }
    
    .material-card {
        padding: var(--spacing-md);
        margin-bottom: var(--spacing-sm);
    }
    
    .material-table th,
    .material-table td {
        padding: var(--spacing-sm);
        font-size: var(--font-sm);
    }
}

@media (max-width: 480px) {
    :root {
        --spacing-lg: 16px;
        --spacing-xl: 24px;
        --spacing-xxl: 32px;
    }
    
    .material-card {
        padding: var(--spacing-sm);
        border-radius: var(--radius-small);
    }
    
    .btn-material {
        padding: 10px 20px;
        font-size: var(--font-sm);
    }
}
