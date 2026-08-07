<?php
/**
 * Built-in tracking service definitions used by the auto-blocker.
 *
 * @package TFM_Tracking_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registry of known third-party tracking services, the URL/inline
 * signatures that identify them, and the consent category they need.
 */
class TFM_TC_Services {

	/**
	 * All built-in services.
	 *
	 * Each service: label, category, patterns (matched against script src,
	 * inline script contents and iframe src, case-insensitive substring).
	 *
	 * @return array
	 */
	public static function all() {
		$services = array(
			'google-analytics'  => array(
				'label'    => 'Google Analytics (GA4)',
				'category' => 'analytics',
				'patterns' => array( 'google-analytics.com', 'googletagmanager.com/gtag/js', 'analytics.google.com' ),
			),
			'google-tag-manager' => array(
				'label'    => 'Google Tag Manager',
				'category' => 'analytics',
				'patterns' => array( 'googletagmanager.com/gtm.js', 'googletagmanager.com/ns.html' ),
			),
			'google-ads'        => array(
				'label'    => 'Google Ads / Remarketing / DoubleClick',
				'category' => 'advertising',
				'patterns' => array( 'googleadservices.com', 'googlesyndication.com', 'doubleclick.net', 'google.com/pagead', 'googletagservices.com' ),
			),
			'facebook-pixel'    => array(
				'label'    => 'Meta (Facebook) Pixel',
				'category' => 'advertising',
				'patterns' => array( 'connect.facebook.net', 'facebook.com/tr', 'fbq(' ),
			),
			'linkedin-insight'  => array(
				'label'    => 'LinkedIn Insight Tag',
				'category' => 'advertising',
				'patterns' => array( 'snap.licdn.com', 'px.ads.linkedin.com', '_linkedin_partner_id' ),
			),
			'microsoft-clarity' => array(
				'label'    => 'Microsoft Clarity',
				'category' => 'analytics',
				'patterns' => array( 'clarity.ms' ),
			),
			'microsoft-ads'     => array(
				'label'    => 'Microsoft Ads (Bing UET)',
				'category' => 'advertising',
				'patterns' => array( 'bat.bing.com', 'uetq' ),
			),
			'hotjar'            => array(
				'label'    => 'Hotjar',
				'category' => 'analytics',
				'patterns' => array( 'hotjar.com', '_hjSettings' ),
			),
			'tiktok'            => array(
				'label'    => 'TikTok Pixel',
				'category' => 'advertising',
				'patterns' => array( 'analytics.tiktok.com', 'ttq.load' ),
			),
			'twitter-pixel'     => array(
				'label'    => 'X (Twitter) Pixel',
				'category' => 'advertising',
				'patterns' => array( 'static.ads-twitter.com', 'twq(' ),
			),
			'pinterest'         => array(
				'label'    => 'Pinterest Tag',
				'category' => 'advertising',
				'patterns' => array( 's.pinimg.com/ct', 'pintrk(' ),
			),
			'snapchat'          => array(
				'label'    => 'Snapchat Pixel',
				'category' => 'advertising',
				'patterns' => array( 'sc-static.net', 'snaptr(' ),
			),
			'hubspot'           => array(
				'label'    => 'HubSpot Tracking',
				'category' => 'advertising',
				'patterns' => array( 'js.hs-scripts.com', 'js.hs-analytics.net', 'js.hs-banner.com', 'track.hubspot.com', 'js.hsadspixel.net', 'js.hscollectedforms.net' ),
			),
			'activecampaign'    => array(
				'label'    => 'ActiveCampaign Site Tracking',
				'category' => 'advertising',
				'patterns' => array( 'trackcmp.net', 'vgo(' ),
			),
			'salesforce-pardot' => array(
				'label'    => 'Salesforce / Pardot',
				'category' => 'advertising',
				'patterns' => array( 'pi.pardot.com', 'pardot.com/pd.js' ),
			),
			'callrail'          => array(
				'label'    => 'CallRail (call tracking)',
				'category' => 'advertising',
				'patterns' => array( 'cdn.callrail.com', 'callrail.com/companies' ),
			),
			'whatconverts'      => array(
				'label'    => 'WhatConverts (lead & call tracking)',
				'category' => 'advertising',
				// Ships from a randomised CloudFront hostname specifically to
				// evade ad blockers — which also means no cookie scanner detects
				// it and it matches nothing generic. Across the sites we manage
				// the hostname is shared (only the numeric script id differs), so
				// it can be matched directly. iconnode.com is the vendor's stable
				// backend, and $wc_leads catches the inline bootstrap should the
				// CDN hostname ever rotate.
				'patterns' => array( 'ksrndkehqnwntyxlhgto.com', 'iconnode.com', '$wc_leads' ),
			),
			'segment'           => array(
				'label'    => 'Segment',
				'category' => 'analytics',
				'patterns' => array( 'cdn.segment.com' ),
			),
			'crazyegg'          => array(
				'label'    => 'Crazy Egg',
				'category' => 'analytics',
				'patterns' => array( 'script.crazyegg.com' ),
			),
			'luckyorange'       => array(
				'label'    => 'Lucky Orange',
				'category' => 'analytics',
				'patterns' => array( 'luckyorange.com', 'luckyorange.net' ),
			),
			'intercom'          => array(
				'label'    => 'Intercom Chat',
				'category' => 'functional',
				'patterns' => array( 'widget.intercom.io', 'js.intercomcdn.com' ),
			),
			'drift'             => array(
				'label'    => 'Drift Chat',
				'category' => 'functional',
				'patterns' => array( 'js.driftt.com' ),
			),
			'tawk'              => array(
				'label'    => 'Tawk.to Chat',
				'category' => 'functional',
				'patterns' => array( 'embed.tawk.to' ),
			),
			'youtube'           => array(
				'label'    => 'YouTube Embeds',
				'category' => 'functional',
				// iframe_api/player_api cover players injected client-side
				// (e.g. Elementor video widgets) before the embed exists.
				'patterns' => array( 'youtube.com/embed', 'youtube.com/iframe_api', 'youtube.com/player_api' ),
			),
			'vimeo'             => array(
				'label'    => 'Vimeo Embeds',
				'category' => 'functional',
				'patterns' => array( 'player.vimeo.com' ),
			),
			'google-maps'       => array(
				'label'    => 'Google Maps Embeds',
				'category' => 'functional',
				'patterns' => array( 'google.com/maps/embed', 'maps.googleapis.com' ),
			),
		);

		/**
		 * Filter the built-in service registry.
		 *
		 * @param array $services Service definitions.
		 */
		return apply_filters( 'tfm_tc_services', $services );
	}

	/**
	 * Flattened pattern => category map for the blocker, honoring
	 * disabled services and the GTM Consent Mode setting.
	 *
	 * @return array
	 */
	public static function active_patterns() {
		$disabled = (array) TFM_TC_Settings::get( 'disabled_services', array() );
		$map      = array();

		foreach ( self::all() as $slug => $service ) {
			if ( in_array( $slug, $disabled, true ) ) {
				continue;
			}
			// When GTM runs in Consent Mode, GTM itself must load so its
			// consent-gated tags can honor the signals; do not block it.
			if ( 'google-tag-manager' === $slug && 'consent_mode' === TFM_TC_Settings::get( 'gtm_mode' ) && TFM_TC_Settings::get( 'consent_mode' ) ) {
				continue;
			}
			foreach ( $service['patterns'] as $pattern ) {
				$map[ $pattern ] = $service['category'];
			}
		}

		foreach ( TFM_TC_Settings::custom_patterns() as $pattern => $category ) {
			$map[ $pattern ] = $category;
		}

		/**
		 * Filter the final pattern => category map used for blocking.
		 *
		 * @param array $map Pattern map.
		 */
		return apply_filters( 'tfm_tc_active_patterns', $map );
	}
}
