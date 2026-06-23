<?php
// =============================================
// SECURITY & CONFIGURATION
// =============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true
    ]);
}

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

require_once 'includes/functions.php';
$page_title = "About Us - Green Agric Nigeria";
$page_css = 'about.css';
$page_description = "Green Agric Nigeria is a technology-enabled agricultural supply chain company committed to strengthening food systems.";
include 'includes/header.php';
?>

<style>
/* ============================================
   ROOT VARIABLES
   ============================================ */
:root {
    --primary: #0d6e3f;
    --primary-dark: #0a5230;
    --primary-light: #e8f5ee;
    --primary-gradient: linear-gradient(135deg, #0a5230 0%, #1a8a4d 50%, #0d6e3f 100%);
    --secondary: #2d7d4f;
    --accent: #f5a623;
    --text-dark: #1a2a3a;
    --text-muted: #6b7a8a;
    --bg-light: #f7faf9;
    --shadow-sm: 0 2px 20px rgba(13, 110, 63, 0.08);
    --shadow-md: 0 10px 40px rgba(13, 110, 63, 0.12);
    --shadow-lg: 0 20px 60px rgba(13, 110, 63, 0.15);
    --shadow-xl: 0 30px 80px rgba(13, 110, 63, 0.2);
    --radius-sm: 8px;
    --radius-md: 16px;
    --radius-lg: 24px;
    --radius-xl: 32px;
    --transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* ============================================
   GLOBAL STYLES
   ============================================ */
body {
    background: var(--bg-light);
    color: var(--text-dark);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

/* ============================================
   HERO SECTION - MODERN GLASSMORPHISM
   ============================================ */
.about-hero {
    position: relative;
    min-height: 100vh;
    display: flex;
    align-items: center;
    overflow: hidden;
    background: var(--primary-gradient);
    padding: 30px 0 80px;
}

/* Animated Background */
.about-hero .bg-pattern {
    position: absolute;
    inset: 0;
    opacity: 0.08;
    background-image: 
        radial-gradient(circle at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(255,255,255,0.05) 0%, transparent 40%),
        radial-gradient(circle at 50% 80%, rgba(255,255,255,0.08) 0%, transparent 50%);
    animation: pulseBackground 8s ease-in-out infinite;
}

@keyframes pulseBackground {
    0%, 100% { transform: scale(1); opacity: 0.08; }
    50% { transform: scale(1.05); opacity: 0.12; }
}

/* Floating Orbs */
.floating-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(60px);
    animation: floatOrb 20s ease-in-out infinite;
}

.floating-orb.orb-1 {
    width: 400px;
    height: 400px;
    background: rgba(255, 255, 255, 0.08);
    top: -100px;
    right: -100px;
    animation-delay: 0s;
}

.floating-orb.orb-2 {
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.05);
    bottom: -50px;
    left: -50px;
    animation-delay: -7s;
}

.floating-orb.orb-3 {
    width: 200px;
    height: 200px;
    background: rgba(255, 255, 255, 0.06);
    top: 40%;
    right: 20%;
    animation-delay: -14s;
}

@keyframes floatOrb {
    0%, 100% { transform: translate(0, 0) scale(1); }
    25% { transform: translate(50px, -50px) scale(1.1); }
    50% { transform: translate(-30px, 30px) scale(0.9); }
    75% { transform: translate(40px, 20px) scale(1.05); }
}

/* Hero Content */
.hero-content {
    position: relative;
    z-index: 2;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    padding: 8px 20px 8px 12px;
    border-radius: 50px;
    color: white;
    font-size: 0.85rem;
    font-weight: 500;
    margin-bottom: 30px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    animation: slideDown 0.8s ease-out;
}

.hero-badge .dot {
    width: 8px;
    height: 8px;
    background: #4ade80;
    border-radius: 50%;
    display: inline-block;
    animation: pulseDot 2s ease-in-out infinite;
}

@keyframes pulseDot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

.hero-title {
    font-size: 4.5rem;
    font-weight: 800;
    color: white;
    line-height: 1.1;
    margin-bottom: 25px;
    animation: slideUp 0.8s ease-out 0.2s both;
}

.hero-title .highlight {
    background: linear-gradient(135deg, #fcd34d, #f59e0b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-description {
    font-size: 1.25rem;
    color: rgba(255, 255, 255, 0.9);
    max-width: 600px;
    line-height: 1.8;
    margin-bottom: 40px;
    animation: slideUp 0.8s ease-out 0.4s both;
}

.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    animation: slideUp 0.8s ease-out 0.6s both;
}

.btn-hero-primary {
    background: white;
    color: var(--primary-dark);
    padding: 16px 40px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    border: none;
    transition: var(--transition);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.btn-hero-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    background: white;
    color: var(--primary-dark);
}

.btn-hero-secondary {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    color: white;
    padding: 16px 40px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: var(--transition);
}

.btn-hero-secondary:hover {
    background: rgba(255, 255, 255, 0.25);
    color: white;
    transform: translateY(-3px);
    border-color: rgba(255, 255, 255, 0.3);
}

/* Hero Stats */
.hero-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-top: 60px;
    padding-top: 40px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    animation: slideUp 0.8s ease-out 0.8s both;
}

.hero-stat {
    text-align: center;
}

.hero-stat .number {
    font-size: 2.5rem;
    font-weight: 700;
    color: white;
    display: block;
    line-height: 1.2;
}

.hero-stat .number .plus {
    color: #fcd34d;
}

.hero-stat .label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    margin-top: 4px;
}

/* Hero Image */
.hero-visual {
    position: relative;
    z-index: 2;
    animation: slideUp 0.8s ease-out 0.4s both;
}

.hero-image-wrapper {
    position: relative;
    border-radius: var(--radius-xl);
    overflow: hidden;
    box-shadow: 0 30px 80px rgba(0, 0, 0, 0.3);
}

.hero-image-wrapper img {
    width: 100%;
    height: 500px;
    object-fit: cover;
    display: block;
}

.hero-image-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(10, 82, 48, 0.3), transparent);
}

/* Floating Card on Hero */
.floating-card {
    position: absolute;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 16px 24px;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    display: flex;
    align-items: center;
    gap: 14px;
    animation: floatCard 6s ease-in-out infinite;
}

.floating-card.card-1 {
    top: 30px;
    right: -20px;
    animation-delay: 0s;
}

.floating-card.card-2 {
    bottom: 40px;
    left: -20px;
    animation-delay: -3s;
}

.floating-card .icon {
    width: 44px;
    height: 44px;
    background: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.2rem;
}

.floating-card .text .title {
    font-weight: 600;
    color: var(--text-dark);
    font-size: 0.9rem;
}

.floating-card .text .subtitle {
    color: var(--text-muted);
    font-size: 0.8rem;
}

@keyframes floatCard {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ============================================
   SECTION COMMON STYLES
   ============================================ */
.section-padding {
    padding: 100px 0;
    padding-top:20px
}

.section-header {
    text-align: center;
    max-width: 700px;
    margin: 0 auto 60px;
}

.section-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 16px;
    background: var(--primary-light);
    color: var(--primary);
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin-bottom: 16px;
}

.section-title {
    font-size: 3rem;
    font-weight: 800;
    color: var(--text-dark);
    line-height: 1.2;
    margin-bottom: 16px;
}

.section-title .highlight {
    color: var(--primary);
}

.section-subtitle {
    font-size: 1.15rem;
    color: var(--text-muted);
    line-height: 1.8;
}

/* ============================================
   ABOUT INTRODUCTION - MODERN LAYOUT
   ============================================ */
.about-intro {
    background: white;
    position: relative;
}

.about-intro::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
}

.intro-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
}

.intro-content .text-lg {
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--text-dark);
    line-height: 1.6;
    margin-bottom: 24px;
}

.intro-content .text-muted-lg {
    font-size: 1.05rem;
    color: var(--text-muted);
    line-height: 1.8;
}

.intro-features {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 30px;
}

.intro-feature {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--bg-light);
    border-radius: var(--radius-sm);
    transition: var(--transition);
}

.intro-feature:hover {
    background: var(--primary-light);
    transform: translateX(5px);
}

.intro-feature .icon {
    width: 32px;
    height: 32px;
    background: var(--primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.intro-feature .text {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-dark);
}

/* Intro Visual */
.intro-visual {
    position: relative;
}

.intro-visual .main-image {
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}

.intro-visual .main-image img {
    width: 100%;
    height: 450px;
    object-fit: cover;
}

.experience-badge {
    position: absolute;
    bottom: -20px;
    right: -20px;
    background: white;
    padding: 24px 32px;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    text-align: center;
}

.experience-badge .number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--primary);
    display: block;
    line-height: 1;
}

.experience-badge .label {
    font-size: 0.9rem;
    color: var(--text-muted);
}

/* ============================================
   WHAT WE DO - MODERN CARDS
   ============================================ */
.what-we-do {
    background: var(--bg-light);
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
}

.service-card {
    background: white;
    padding: 40px 30px;
    border-radius: var(--radius-md);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.04);
}

.service-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
    opacity: 0;
    transition: var(--transition);
}

.service-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-md);
    border-color: rgba(13, 110, 63, 0.1);
}

.service-card:hover::before {
    opacity: 1;
}

.service-card .icon-wrapper {
    width: 64px;
    height: 64px;
    background: var(--primary-light);
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    transition: var(--transition);
}

.service-card:hover .icon-wrapper {
    background: var(--primary);
    transform: scale(1.05) rotate(-5deg);
}

.service-card .icon-wrapper i {
    font-size: 1.8rem;
    color: var(--primary);
    transition: var(--transition);
}

.service-card:hover .icon-wrapper i {
    color: white;
}

.service-card .service-number {
    position: absolute;
    top: 20px;
    right: 20px;
    font-size: 3rem;
    font-weight: 800;
    color: rgba(13, 110, 63, 0.05);
    line-height: 1;
}

.service-card h4 {
    font-weight: 700;
    margin-bottom: 12px;
    color: var(--text-dark);
}

.service-card p {
    color: var(--text-muted);
    line-height: 1.7;
    margin-bottom: 0;
}

/* ============================================
   MISSION & VISION - SPLIT LAYOUT
   ============================================ */
.mission-vision {
    background: white;
}

.mv-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
}

.mv-card {
    padding: 50px 40px;
    border-radius: var(--radius-lg);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.mv-card.mission {
    background: linear-gradient(135deg, var(--primary-light), white);
    border: 1px solid rgba(13, 110, 63, 0.1);
}

.mv-card.vision {
    background: linear-gradient(135deg, #fef3c7, white);
    border: 1px solid rgba(245, 166, 35, 0.1);
}

.mv-card .icon-circle {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin-bottom: 24px;
}

.mv-card.mission .icon-circle {
    background: var(--primary);
    color: white;
}

.mv-card.vision .icon-circle {
    background: var(--accent);
    color: white;
}

.mv-card .badge-label {
    display: inline-block;
    padding: 4px 16px;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}

.mv-card.mission .badge-label {
    background: var(--primary);
    color: white;
}

.mv-card.vision .badge-label {
    background: var(--accent);
    color: white;
}

.mv-card h3 {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 16px;
}

.mv-card p {
    font-size: 1.05rem;
    line-height: 1.8;
    color: var(--text-muted);
    margin-bottom: 0;
}

.mv-card .decorative-line {
    width: 60px;
    height: 3px;
    border-radius: 2px;
    margin-top: 20px;
}

.mv-card.mission .decorative-line {
    background: var(--primary);
}

.mv-card.vision .decorative-line {
    background: var(--accent);
}

/* ============================================
   VALUES - MODERN GRID WITH ICONS
   ============================================ */
.values-section {
    background: var(--bg-light);
}

.values-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
}

.value-card {
    background: white;
    padding: 32px 20px;
    border-radius: var(--radius-md);
    text-align: center;
    transition: var(--transition);
    border: 1px solid rgba(0, 0, 0, 0.04);
    position: relative;
}

.value-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-md);
    border-color: var(--primary);
}

.value-card .icon-circle {
    width: 56px;
    height: 56px;
    background: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    transition: var(--transition);
}

.value-card:hover .icon-circle {
    background: var(--primary);
    transform: rotateY(360deg);
}

.value-card .icon-circle i {
    font-size: 1.5rem;
    color: var(--primary);
    transition: var(--transition);
}

.value-card:hover .icon-circle i {
    color: white;
}

.value-card h6 {
    font-weight: 700;
    margin-bottom: 8px;
    color: var(--text-dark);
}

.value-card p {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 0;
    line-height: 1.5;
}

.value-card .value-number {
    position: absolute;
    top: 10px;
    right: 14px;
    font-size: 0.7rem;
    font-weight: 700;
    color: rgba(13, 110, 63, 0.15);
}

/* ============================================
   COMMITMENT - WITH TIMELINE STYLE
   ============================================ */
.commitment-section {
    background: white;
    position: relative;
}

.commitment-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
}

.commitment-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.commitment-list li {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 16px 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.04);
    transition: var(--transition);
    cursor: default;
}

.commitment-list li:last-child {
    border-bottom: none;
}

.commitment-list li:hover {
    padding-left: 10px;
}

.commitment-list li .icon {
    width: 40px;
    height: 40px;
    background: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    flex-shrink: 0;
    transition: var(--transition);
}

.commitment-list li:hover .icon {
    background: var(--primary);
    color: white;
}

.commitment-list li .content h6 {
    font-weight: 600;
    margin-bottom: 4px;
    color: var(--text-dark);
}

.commitment-list li .content p {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin: 0;
}

.commitment-visual {
    position: relative;
}

.commitment-visual .stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.commitment-visual .stat-box {
    background: var(--bg-light);
    padding: 24px;
    border-radius: var(--radius-md);
    text-align: center;
    transition: var(--transition);
}

.commitment-visual .stat-box:hover {
    background: var(--primary-light);
    transform: scale(1.02);
}

.commitment-visual .stat-box .number {
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--primary);
    display: block;
}

.commitment-visual .stat-box .label {
    font-size: 0.85rem;
    color: var(--text-muted);
}

/* ============================================
   WHO WE SERVE - MODERN LOGO GRID
   ============================================ */
.serve-section {
    background: var(--bg-light);
}

.serve-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

.serve-item {
    background: white;
    padding: 30px 20px;
    border-radius: var(--radius-md);
    text-align: center;
    transition: var(--transition);
    border: 1px solid rgba(0, 0, 0, 0.04);
    cursor: default;
}

.serve-item:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
    border-color: var(--primary);
}

.serve-item .icon {
    width: 48px;
    height: 48px;
    background: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    transition: var(--transition);
}

.serve-item:hover .icon {
    background: var(--primary);
}

.serve-item .icon i {
    font-size: 1.3rem;
    color: var(--primary);
    transition: var(--transition);
}

.serve-item:hover .icon i {
    color: white;
}

.serve-item h6 {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 0;
}

/* ============================================
   CTA - MODERN WITH GRADIENT
   ============================================ */
.cta-section {
    background: var(--primary-gradient);
    padding: 80px 0;
    position: relative;
    overflow: hidden;
}

.cta-section::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        radial-gradient(circle at 20% 50%, rgba(255,255,255,0.05) 0%, transparent 50%),
        radial-gradient(circle at 80% 50%, rgba(255,255,255,0.05) 0%, transparent 50%);
}

.cta-section .container {
    position: relative;
    z-index: 2;
}

.cta-content {
    text-align: center;
    color: white;
}

.cta-content h2 {
    font-size: 3rem;
    font-weight: 800;
    margin-bottom: 16px;
}

.cta-content p {
    font-size: 1.2rem;
    opacity: 0.9;
    max-width: 600px;
    margin: 0 auto 40px;
    line-height: 1.8;
}

.cta-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    justify-content: center;
}

.btn-cta-primary {
    background: white;
    color: var(--primary-dark);
    padding: 16px 44px;
    border-radius: 50px;
    font-weight: 600;
    border: none;
    transition: var(--transition);
    font-size: 1rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.btn-cta-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
    background: white;
    color: var(--primary-dark);
}

.btn-cta-secondary {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    color: white;
    padding: 16px 44px;
    border-radius: 50px;
    font-weight: 600;
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: var(--transition);
    font-size: 1rem;
}

.btn-cta-secondary:hover {
    background: rgba(255, 255, 255, 0.25);
    color: white;
    transform: translateY(-3px);
}

/* ============================================
   RESPONSIVE DESIGN
   ============================================ */
@media (max-width: 1200px) {
    .hero-title {
        font-size: 3.8rem;
    }
    
    .values-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 992px) {
    .about-hero {
        min-height: auto;
        padding: 100px 0 60px;
    }
    
    .hero-title {
        font-size: 3rem;
    }
    
    .hero-stats {
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }
    
    .intro-grid {
        grid-template-columns: 1fr;
        gap: 40px;
    }
    
    .intro-visual .main-image img {
        height: 350px;
    }
    
    .services-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .mv-grid {
        grid-template-columns: 1fr;
    }
    
    .values-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .commitment-grid {
        grid-template-columns: 1fr;
        gap: 40px;
    }
    
    .serve-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .section-title {
        font-size: 2.5rem;
    }
}

@media (max-width: 768px) {
    .about-hero {
        padding: 80px 0 40px;
    }
    
    .hero-title {
        font-size: 2.5rem;
    }
    
    .hero-description {
        font-size: 1rem;
    }
    
    .hero-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .hero-actions .btn {
        text-align: center;
        justify-content: center;
    }
    
    .hero-stats {
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-top: 40px;
        padding-top: 30px;
    }
    
    .hero-stat .number {
        font-size: 1.8rem;
    }
    
    .floating-card {
        display: none;
    }
    
    .hero-image-wrapper img {
        height: 300px;
    }
    
    .services-grid {
        grid-template-columns: 1fr;
    }
    
    .values-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .intro-features {
        grid-template-columns: 1fr;
    }
    
    .section-padding {
        padding: 60px 0;
    }
    
    .section-title {
        font-size: 2rem;
    }
    
    .experience-badge {
        position: relative;
        bottom: auto;
        right: auto;
        margin-top: 20px;
    }
    
    .cta-content h2 {
        font-size: 2rem;
    }
    
    .commitment-visual .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 576px) {
    .hero-title {
        font-size: 2rem;
    }
    
    .hero-stats {
        grid-template-columns: 1fr 1fr 1fr;
        gap: 10px;
    }
    
    .hero-stat .number {
        font-size: 1.4rem;
    }
    
    .hero-stat .label {
        font-size: 0.75rem;
    }
    
    .values-grid {
        grid-template-columns: 1fr;
    }
    
    .serve-grid {
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    
    .serve-item {
        padding: 20px 12px;
    }
    
    .serve-item h6 {
        font-size: 0.8rem;
    }
    
    .section-title {
        font-size: 1.6rem;
    }
    
    .mv-card {
        padding: 30px 24px;
    }
    
    .commitment-visual .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    
    .commitment-visual .stat-box {
        padding: 16px;
    }
    
    .commitment-visual .stat-box .number {
        font-size: 1.6rem;
    }
}

/* ============================================
   ANIMATIONS
   ============================================ */
.reveal {
    opacity: 0;
    transform: translateY(40px);
    transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.reveal.visible {
    opacity: 1;
    transform: translateY(0);
}

.reveal-delay-1 { transition-delay: 0.1s; }
.reveal-delay-2 { transition-delay: 0.2s; }
.reveal-delay-3 { transition-delay: 0.3s; }
.reveal-delay-4 { transition-delay: 0.4s; }
.reveal-delay-5 { transition-delay: 0.5s; }

/* ============================================
   SCROLL PROGRESS BAR
   ============================================ */
.scroll-progress {
    position: fixed;
    top: 0;
    left: 0;
    width: 0;
    height: 3px;
    background: var(--primary-gradient);
    z-index: 9999;
    transition: width 0.1s linear;
}

/* ============================================
   PRINT STYLES
   ============================================ */
@media print {
    .about-hero {
        min-height: auto;
        padding: 40px 0;
    }
    
    .floating-orb,
    .floating-card,
    .scroll-progress {
        display: none !important;
    }
    
    .cta-section {
        display: none;
    }
    
    .service-card:hover {
        transform: none !important;
    }
}
</style>

<!-- ============================================
   SCROLL PROGRESS BAR
   ============================================ -->
<div class="scroll-progress" id="scrollProgress"></div>

<!-- ============================================
   HERO SECTION - MODERN GLASSMORPHISM
   ============================================ -->
<section class="about-hero">
    <div class="bg-pattern"></div>
    <div class="floating-orb orb-1"></div>
    <div class="floating-orb orb-2"></div>
    <div class="floating-orb orb-3"></div>

    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="hero-content">
                    <div class="hero-badge">
                        <span class="dot"></span>
                        Trusted Agricultural Partner
                    </div>
                    
                    <h1 class="hero-title">
                        Strengthening<br>
                        <span class="highlight">Nigeria's Food</span><br>
                        Ecosystem
                    </h1>
                    
                    <p class="hero-description">
                        Technology‑enabled agricultural supply chain — delivering consistent, 
                        high‑quality produce to businesses across Lagos and major commercial hubs.
                    </p>
                    
                    <div class="hero-actions">
                        <a href="<?php echo BASE_URL; ?>/contact.php" class="btn-hero-primary">
                            <i class="bi bi-handshake me-2"></i> Partner With Us
                        </a>
                        <a href="<?php echo BASE_URL; ?>/contact.php?type=seller" class="btn-hero-secondary">
                            <i class="bi bi-person-plus me-2"></i> Join as Farmer
                        </a>
                    </div>
                    
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <span class="number">500<span class="plus">+</span></span>
                            <span class="label">Farm Partners</span>
                        </div>
                        <div class="hero-stat">
                            <span class="number">200<span class="plus">+</span></span>
                            <span class="label">Business Clients</span>
                        </div>
                        <div class="hero-stat">
                            <span class="number">98<span class="plus">%</span></span>
                            <span class="label">Satisfaction Rate</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="hero-visual">
                    <div class="hero-image-wrapper">
                        <img src="<?php echo BASE_URL; ?>/assets/images/about-hero.png" 
                             alt="Green Agric Nigeria - Agricultural Supply Chain" 
                             loading="lazy">
                        <div class="hero-image-overlay"></div>
                    </div>
                    
                    <!-- Floating Cards -->
                    <div class="floating-card card-1">
                        <div class="icon">
                            <i class="bi bi-truck"></i>
                        </div>
                        <div class="text">
                            <div class="title">Nationwide Delivery</div>
                            <div class="subtitle">15+ states covered</div>
                        </div>
                    </div>
                    
                    <div class="floating-card card-2">
                        <div class="icon">
                            <i class="bi bi-leaf"></i>
                        </div>
                        <div class="text">
                            <div class="title">100% Organic</div>
                            <div class="subtitle">Certified produce</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
   ABOUT INTRODUCTION
   ============================================ -->
<section class="about-intro section-padding">
    <div class="container">
        <div class="intro-grid">
            <div class="intro-content reveal">
                <div class="section-tag">
                    <i class="bi bi-building"></i> About Us
                </div>
                <h2 class="section-title" style="text-align: left;">
                    Technology-Driven<br>
                    <span class="highlight">Agricultural Supply Chain</span>
                </h2>
                <p class="text-lg">
                    Green Agric Nigeria is a cross‑continental agritech initiative operating in both Nigeria and Canada.
                </p>
                <p class="text-muted-lg">
                    Our Nigerian operations focus on aggregation, wholesale distribution, and transparent market access, 
                    supported by digital tools that enhance traceability, pricing clarity, and operational efficiency.
                </p>
                
                <div class="intro-features">
                    <div class="intro-feature">
                        <div class="icon"><i class="bi bi-check-lg"></i></div>
                        <span class="text">Cross-Continental Expertise</span>
                    </div>
                    <div class="intro-feature">
                        <div class="icon"><i class="bi bi-check-lg"></i></div>
                        <span class="text">Digital Traceability</span>
                    </div>
                    <div class="intro-feature">
                        <div class="icon"><i class="bi bi-check-lg"></i></div>
                        <span class="text">Transparent Operations</span>
                    </div>
                    <div class="intro-feature">
                        <div class="icon"><i class="bi bi-check-lg"></i></div>
                        <span class="text">Professional Standards</span>
                    </div>
                </div>
            </div>
            
            <div class="intro-visual reveal reveal-delay-2">
                <div class="main-image">
                    <img src="<?php echo BASE_URL; ?>/assets/images/about-team.png" 
                         alt="Green Agric Nigeria Team" 
                         loading="lazy">
                </div>
                <div class="experience-badge">
                    <span class="number">10+</span>
                    <span class="label">Years Experience</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
   WHAT WE DO - SERVICES
   ============================================ -->
<section class="what-we-do section-padding">
    <div class="container">
        <div class="section-header reveal">
            <div class="section-tag">
                <i class="bi bi-gear"></i> What We Do
            </div>
            <h2 class="section-title">
                Our <span class="highlight">Services</span>
            </h2>
            <p class="section-subtitle">
                Comprehensive agricultural supply chain solutions from farm to table
            </p>
        </div>
        
        <div class="services-grid">
            <div class="service-card reveal reveal-delay-1">
                <span class="service-number">01</span>
                <div class="icon-wrapper">
                    <i class="bi bi-truck"></i>
                </div>
                <h4>Wholesale Distribution</h4>
                <p>Supplying supermarkets, restaurants, hotels, processors, and market traders with consistent volumes of fresh produce.</p>
            </div>
            
            <div class="service-card reveal reveal-delay-2">
                <span class="service-number">02</span>
                <div class="icon-wrapper">
                    <i class="bi bi-boxes"></i>
                </div>
                <h4>Aggregation Services</h4>
                <p>Sourcing directly from verified farmers and cooperatives to ensure quality and reduce post‑harvest losses.</p>
            </div>
            
            <div class="service-card reveal reveal-delay-3">
                <span class="service-number">03</span>
                <div class="icon-wrapper">
                    <i class="bi bi-shop"></i>
                </div>
                <h4>Market Access for Farmers</h4>
                <p>Providing farmers with stable demand, fair pricing, and transparent payment systems.</p>
            </div>
            
            <div class="service-card reveal reveal-delay-1">
                <span class="service-number">04</span>
                <div class="icon-wrapper">
                    <i class="bi bi-check-circle"></i>
                </div>
                <h4>Quality Assurance</h4>
                <p>Implementing standardized grading, sorting, and handling processes to meet commercial buyer requirements.</p>
            </div>
            
            <div class="service-card reveal reveal-delay-2">
                <span class="service-number">05</span>
                <div class="icon-wrapper">
                    <i class="bi bi-diagram-2"></i>
                </div>
                <h4>Logistics Coordination</h4>
                <p>Managing the movement of produce from farm clusters to urban markets with efficiency and reliability.</p>
            </div>
            
            <div class="service-card reveal reveal-delay-3">
                <span class="service-number">06</span>
                <div class="icon-wrapper">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <h4>Value Chain Optimization</h4>
                <p>Leveraging technology to enhance traceability, pricing clarity, and operational efficiency.</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
   MISSION & VISION
   ============================================ -->
<section class="mission-vision section-padding">
    <div class="container">
        <div class="section-header reveal">
            <div class="section-tag">
                <i class="bi bi-compass"></i> Our Direction
            </div>
            <h2 class="section-title">
                Mission &amp; <span class="highlight">Vision</span>
            </h2>
        </div>
        
        <div class="mv-grid">
            <div class="mv-card mission reveal">
                <span class="badge-label">Mission</span>
                <div class="icon-circle">
                    <i class="bi bi-bullseye"></i>
                </div>
                <h3>Empowering Farmers, Strengthening Food Systems</h3>
                <p>
                    To build a transparent, efficient, and scalable agricultural supply chain that empowers farmers, 
                    supports businesses, and strengthens Nigeria's food ecosystem.
                </p>
                <div class="decorative-line"></div>
            </div>
            
            <div class="mv-card vision reveal reveal-delay-2">
                <span class="badge-label">Vision</span>
                <div class="icon-circle">
                    <i class="bi bi-eye"></i>
                </div>
                <h3>Nigeria's Most Trusted Agricultural Partner</h3>
                <p>
                    To become Nigeria's most trusted agricultural distribution partner—recognized for reliability, 
                    quality, and innovation—while contributing to national food security and sustainable economic growth.
                </p>
                <div class="decorative-line"></div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
   CORE VALUES
   ============================================ -->
<section class="values-section section-padding">
    <div class="container">
        <div class="section-header reveal">
            <div class="section-tag">
                <i class="bi bi-star"></i> Our Values
            </div>
            <h2 class="section-title">
                Core <span class="highlight">Values</span>
            </h2>
            <p class="section-subtitle">
                The principles that guide everything we do
            </p>
        </div>
        
        <div class="values-grid">
            <div class="value-card reveal reveal-delay-1">
                <span class="value-number">01</span>
                <div class="icon-circle">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h6>Integrity</h6>
                <p>We operate with honesty, accountability, and fairness.</p>
            </div>
            
            <div class="value-card reveal reveal-delay-2">
                <span class="value-number">02</span>
                <div class="icon-circle">
                    <i class="bi bi-eye"></i>
                </div>
                <h6>Transparency</h6>
                <p>Clear pricing, clear processes, and clear communication.</p>
            </div>
            
            <div class="value-card reveal reveal-delay-3">
                <span class="value-number">03</span>
                <div class="icon-circle">
                    <i class="bi bi-speedometer2"></i>
                </div>
                <h6>Efficiency</h6>
                <p>Streamlined operations that reduce waste and maximize value.</p>
            </div>
            
            <div class="value-card reveal reveal-delay-4">
                <span class="value-number">04</span>
                <div class="icon-circle">
                    <i class="bi bi-tree"></i>
                </div>
                <h6>Sustainability</h6>
                <p>Supporting environmentally responsible and economically viable farming.</p>
            </div>
            
            <div class="value-card reveal reveal-delay-5">
                <span class="value-number">05</span>
                <div class="icon-circle">
                    <i class="bi bi-people"></i>
                </div>
                <h6>Partnership</h6>
                <p>Building long‑term relationships with farmers, buyers, and communities.</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
   COMMITMENT TO NIGERIA
   ============================================ -->
<section class="commitment-section section-padding">
    <div class="container">
        <div class="section-header reveal">
            <div class="section-tag">
                <i class="bi bi-heart"></i> Our Commitment
            </div>
            <h2 class="section-title">
                Commitment to <span class="highlight">Nigeria's Food System</span>
            </h2>
        </div>
        
        <div class="commitment-grid">
            <div class="commitment-content reveal">
                <h4 style="font-weight: 700; margin-bottom: 24px;">
                    Strengthening the Agricultural Sector
                </h4>
                <ul class="commitment-list">
                    <li>
                        <div class="icon"><i class="bi bi-arrow-up-circle"></i></div>
                        <div class="content">
                            <h6>Enhancing Farmer Income</h6>
                            <p>Through structured market access and fair pricing</p>
                        </div>
                    </li>
                    <li>
                        <div class="icon"><i class="bi bi-arrow-down-circle"></i></div>
                        <div class="content">
                            <h6>Reducing Post‑Harvest Losses</h6>
                            <p>With better aggregation and logistics</p>
                        </div>
                    </li>
                    <li>
                        <div class="icon"><i class="bi bi-building"></i></div>
                        <div class="content">
                            <h6>Supporting Businesses</h6>
                            <p>With consistent, high‑quality supply</p>
                        </div>
                    </li>
                    <li>
                        <div class="icon"><i class="bi bi-link"></i></div>
                        <div class="content">
                            <h6>Promoting Traceability</h6>
                            <p>And compliance across the value chain</p>
                        </div>
                    </li>
                    <li>
                        <div class="icon"><i class="bi bi-shield-check"></i></div>
                        <div class="content">
                            <h6>Contributing to Food Security</h6>
                            <p>And economic resilience</p>
                        </div>
                    </li>
                </ul>
            </div>
            
            <div class="commitment-visual reveal reveal-delay-2">
                <div class="stats-grid">
                    <div class="stat-box">
                        <span class="number">500+</span>
                        <span class="label">Farmers Connected</span>
                    </div>
                    <div class="stat-box">
                        <span class="number">50+</span>
                        <span class="label">Communities Served</span>
                    </div>
                    <div class="stat-box">
                        <span class="number">2,500+</span>
                        <span class="label">Tons Distributed</span>
                    </div>
                    <div class="stat-box">
                        <span class="number">30%</span>
                        <span class="label">Waste Reduction</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
   WHO WE SERVE
   ============================================ -->
<section class="serve-section section-padding">
    <div class="container">
        <div class="section-header reveal">
            <div class="section-tag">
                <i class="bi bi-people"></i> Our Clients
            </div>
            <h2 class="section-title">
                Who We <span class="highlight">Serve</span>
            </h2>
            <p class="section-subtitle">
                Trusted by businesses across the agricultural value chain
            </p>
        </div>
        
        <div class="serve-grid">
            <div class="serve-item reveal reveal-delay-1">
                <div class="icon"><i class="bi bi-cart3"></i></div>
                <h6>Retail Supermarkets</h6>
            </div>
            <div class="serve-item reveal reveal-delay-2">
                <div class="icon"><i class="bi bi-egg-fried"></i></div>
                <h6>Restaurants &amp; Hotels</h6>
            </div>
            <div class="serve-item reveal reveal-delay-3">
                <div class="icon"><i class="bi bi-building"></i></div>
                <h6>Food Processors</h6>
            </div>
            <div class="serve-item reveal reveal-delay-4">
                <div class="icon"><i class="bi bi-shop-window"></i></div>
                <h6>Market Traders</h6>
            </div>
            <div class="serve-item reveal reveal-delay-1">
                <div class="icon"><i class="bi bi-box-seam"></i></div>
                <h6>Exporters</h6>
            </div>
            <div class="serve-item reveal reveal-delay-2">
                <div class="icon"><i class="bi bi-bank"></i></div>
                <h6>Institutional Clients</h6>
            </div>
            <div class="serve-item reveal reveal-delay-3">
                <div class="icon"><i class="bi bi-briefcase"></i></div>
                <h6>Corporate Clients</h6>
            </div>
            <div class="serve-item reveal reveal-delay-4">
                <div class="icon"><i class="bi bi-people"></i></div>
                <h6>Cooperatives</h6>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
   CTA SECTION
   ============================================ -->
<section class="cta-section">
    <div class="container">
        <div class="cta-content reveal">
            <div class="hero-badge" style="display: inline-flex; margin-bottom: 24px; background: rgba(255,255,255,0.1);">
                <span class="dot"></span>
                Let's Work Together
            </div>
            <h2>Partner With Us</h2>
            <p>
                Green Agric Nigeria welcomes partnerships with farmers, cooperatives, logistics providers, retailers, 
                and organizations committed to strengthening Nigeria's agricultural value chain.
            </p>
            <div class="cta-buttons">
                <a href="<?php echo BASE_URL; ?>/contact.php" class="btn-cta-primary">
                    <i class="bi bi-handshake me-2"></i> Become a Partner
                </a>
                <a href="<?php echo BASE_URL; ?>/contact.php?type=seller" class="btn-cta-secondary">
                    <i class="bi bi-person-plus me-2"></i> Join as a Farmer
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
   JAVASCRIPT
   ============================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // SCROLL PROGRESS BAR
    // ============================================
    const progressBar = document.getElementById('scrollProgress');
    
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const progress = (scrollTop / scrollHeight) * 100;
        progressBar.style.width = progress + '%';
    }, { passive: true });
    
    // ============================================
    // REVEAL ANIMATIONS
    // ============================================
    const revealElements = document.querySelectorAll('.reveal');
    
    const revealObserver = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.15,
        rootMargin: '0px 0px -50px 0px'
    });
    
    revealElements.forEach(el => revealObserver.observe(el));
    
    // ============================================
    // SMOOTH SCROLL FOR ANCHOR LINKS
    // ============================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                const offset = 80;
                const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - offset;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // ============================================
    // PARALLAX HERO EFFECT
    // ============================================
    const hero = document.querySelector('.about-hero');
    if (hero) {
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            if (scrolled < hero.offsetHeight) {
                const rate = scrolled * 0.3;
                hero.style.backgroundPositionY = rate + 'px';
            }
        }, { passive: true });
    }
    
    // ============================================
    // COUNTER ANIMATION
    // ============================================
    const counters = document.querySelectorAll('.stat-box .number, .hero-stat .number');
    
    counters.forEach(counter => {
        const text = counter.textContent;
        const target = parseInt(text.replace(/[^0-9]/g, ''));
        const suffix = text.replace(/[0-9]/g, '');
        
        if (isNaN(target)) return;
        
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounter(counter, target, suffix);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        
        observer.observe(counter);
    });
    
    function animateCounter(element, target, suffix) {
        let current = 0;
        const increment = Math.ceil(target / 50);
        const duration = 2000;
        const stepTime = Math.floor(duration / 50);
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                element.textContent = target + suffix;
                clearInterval(timer);
            } else {
                element.textContent = current + suffix;
            }
        }, stepTime);
    }
});
</script>

<?php include 'includes/footer.php'; ?>