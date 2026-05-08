const API_URL = "http://localhost:8000/api";
const IMAGES_URL = "http://localhost:8000";

const SPA_ROLLOUT_STORAGE_KEY = "fefuart_spa_rollout";
const SPA_BASE_URL_STORAGE_KEY = "fefuart_spa_base_url";
const SPA_ROLLOUT_SCOPE_STORAGE_KEY = "fefuart_spa_rollout_scope";
const SPA_ROLLOUT_PHASE_STORAGE_KEY = "fefuart_spa_rollout_phase";
const DEV_SPA_BASE_URL = "http://127.0.0.1:4173";

function getDefaultSpaBaseUrl() {
	const origin = window.location.origin;
	const hostname = window.location.hostname;
	const isLocalHost = hostname === "127.0.0.1" || hostname === "localhost";

	if (!origin || origin === "null" || isLocalHost) {
		return DEV_SPA_BASE_URL;
	}

	return `${origin.replace(/\/$/, "")}/spa`;
}

const DEFAULT_SPA_BASE_URL = getDefaultSpaBaseUrl();

const LEGACY_TO_SPA_ROUTE_MAP = Object.freeze({
	"index.html": "/",
	"about.html": "/",
	"services.html": "/catalog",
	"galeria.html": "/catalog",
	"dibujo-encargo.html": "/catalog",
	"letras-infantiles.html": "/catalog",
	"ramo-flores.html": "/catalog",
	"login.html": "/auth",
	"register.html": "/auth",
	"cart.html": "/cart",
	"live-art.html": "/live-art",
	"live-art-info.html": "/live-art",
	"admin.html": "/backoffice",
});

const SPA_ROLLOUT_PHASES = Object.freeze({
	phase1: Object.freeze(["login.html", "register.html", "cart.html"]),
	phase2: Object.freeze([
		"login.html",
		"register.html",
		"cart.html",
		"services.html",
		"galeria.html",
		"dibujo-encargo.html",
		"letras-infantiles.html",
		"ramo-flores.html",
		"live-art.html",
		"live-art-info.html",
	]),
	phase3: "*",
});

function normalizeBaseUrl(url) {
	return String(url || DEFAULT_SPA_BASE_URL).replace(/\/$/, "");
}

function getCurrentLegacyView(pathname) {
	const parts = String(pathname || "").split("/").filter(Boolean);
	return (parts[parts.length - 1] || "").toLowerCase();
}

function normalizeRolloutScope(scopeValue) {
	if (scopeValue === null || scopeValue === undefined) {
		return null;
	}

	const rawValue = String(scopeValue).trim().toLowerCase();

	if (!rawValue) {
		return null;
	}

	if (rawValue === "*" || rawValue === "all") {
		return "*";
	}

	const knownViews = Object.keys(LEGACY_TO_SPA_ROUTE_MAP);
	const normalizedViews = rawValue
		.split(",")
		.map((viewName) => viewName.trim())
		.filter(Boolean)
		.map((viewName) => (viewName.endsWith(".html") ? viewName : `${viewName}.html`))
		.filter((viewName) => knownViews.includes(viewName));

	if (!normalizedViews.length) {
		return null;
	}

	return Array.from(new Set(normalizedViews)).join(",");
}

function normalizeRolloutPhase(phaseValue) {
	if (phaseValue === null || phaseValue === undefined) {
		return null;
	}

	const rawValue = String(phaseValue).trim().toLowerCase();

	if (!rawValue) {
		return null;
	}

	if (!Object.prototype.hasOwnProperty.call(SPA_ROLLOUT_PHASES, rawValue)) {
		return null;
	}

	return rawValue;
}

function getScopeFromRolloutPhase(phaseValue) {
	const phaseName = normalizeRolloutPhase(phaseValue);

	if (!phaseName) {
		return null;
	}

	const phaseScope = SPA_ROLLOUT_PHASES[phaseName];

	if (phaseScope === "*") {
		return "*";
	}

	return normalizeRolloutScope(phaseScope.join(","));
}

function getRolloutScopeSet() {
	const runtimeScope = normalizeRolloutScope(window.FEFUART_SPA_ROLLOUT_SCOPE);
	const storedScope = normalizeRolloutScope(window.localStorage.getItem(SPA_ROLLOUT_SCOPE_STORAGE_KEY));
	const runtimePhaseScope = getScopeFromRolloutPhase(window.FEFUART_SPA_ROLLOUT_PHASE);
	const storedPhaseScope = getScopeFromRolloutPhase(
		window.localStorage.getItem(SPA_ROLLOUT_PHASE_STORAGE_KEY)
	);
	const resolvedScope = runtimeScope || storedScope || runtimePhaseScope || storedPhaseScope;

	if (!resolvedScope || resolvedScope === "*") {
		return null;
	}

	return new Set(resolvedScope.split(","));
}

function isCurrentViewInRolloutScope(currentView) {
	const rolloutScope = getRolloutScopeSet();

	if (!rolloutScope) {
		return true;
	}

	return rolloutScope.has(currentView);
}

function syncRolloutFlagFromQuery() {
	const params = new URLSearchParams(window.location.search);
	const rolloutParam = params.get("spa");
	const spaBaseParam = params.get("spaBase");
	const spaViewsParam = params.get("spaViews");
	const spaPhaseParam = params.get("spaPhase");

	if (rolloutParam === "1") {
		window.localStorage.setItem(SPA_ROLLOUT_STORAGE_KEY, "1");
	}

	if (rolloutParam === "0") {
		window.localStorage.removeItem(SPA_ROLLOUT_STORAGE_KEY);
		window.localStorage.removeItem(SPA_ROLLOUT_SCOPE_STORAGE_KEY);
		window.localStorage.removeItem(SPA_ROLLOUT_PHASE_STORAGE_KEY);
	}

	if (spaBaseParam) {
		window.localStorage.setItem(SPA_BASE_URL_STORAGE_KEY, spaBaseParam);
	}

	if (spaPhaseParam !== null) {
		const normalizedPhase = normalizeRolloutPhase(spaPhaseParam);

		if (normalizedPhase) {
			window.localStorage.setItem(SPA_ROLLOUT_PHASE_STORAGE_KEY, normalizedPhase);
			window.localStorage.removeItem(SPA_ROLLOUT_SCOPE_STORAGE_KEY);
		} else {
			window.localStorage.removeItem(SPA_ROLLOUT_PHASE_STORAGE_KEY);
		}
	}

	if (spaViewsParam !== null) {
		const normalizedScope = normalizeRolloutScope(spaViewsParam);

		if (normalizedScope) {
			window.localStorage.setItem(SPA_ROLLOUT_SCOPE_STORAGE_KEY, normalizedScope);
			window.localStorage.removeItem(SPA_ROLLOUT_PHASE_STORAGE_KEY);
		} else {
			window.localStorage.removeItem(SPA_ROLLOUT_SCOPE_STORAGE_KEY);
		}
	}
}

function isSpaRolloutEnabled() {
	if (window.FEFUART_ENABLE_SPA_ROLLOUT === true) {
		return true;
	}

	return window.localStorage.getItem(SPA_ROLLOUT_STORAGE_KEY) === "1";
}

function redirectLegacyPageToSpa() {
	if (!isSpaRolloutEnabled()) {
		return;
	}

	const currentView = getCurrentLegacyView(window.location.pathname);
	const targetRoute = LEGACY_TO_SPA_ROUTE_MAP[currentView];

	if (!targetRoute) {
		return;
	}

	if (!isCurrentViewInRolloutScope(currentView)) {
		return;
	}

	const storedBaseUrl = window.localStorage.getItem(SPA_BASE_URL_STORAGE_KEY);
	const spaBaseUrl = normalizeBaseUrl(window.FEFUART_SPA_BASE_URL || storedBaseUrl);
	const targetUrl = `${spaBaseUrl}${targetRoute}`;

	if (window.location.href !== targetUrl) {
		window.location.replace(targetUrl);
	}
}

window.API_URL = API_URL;
window.IMAGES_URL = IMAGES_URL;
window.FEFUART_CLIENT_CONFIG = Object.freeze({
	API_URL,
	IMAGES_URL,
	SPA_ROLLOUT_STORAGE_KEY,
	SPA_BASE_URL_STORAGE_KEY,
	SPA_ROLLOUT_SCOPE_STORAGE_KEY,
	SPA_ROLLOUT_PHASE_STORAGE_KEY,
	DEV_SPA_BASE_URL,
	DEFAULT_SPA_BASE_URL,
	LEGACY_TO_SPA_ROUTE_MAP,
	SPA_ROLLOUT_PHASES,
});

try {
	syncRolloutFlagFromQuery();
	redirectLegacyPageToSpa();
} catch (error) {
	console.warn("Could not evaluate SPA rollout gate", error);
}