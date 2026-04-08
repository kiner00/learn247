export const REACTIONS = [
    { type: "like", emoji: "👍", label: "Like" },
    { type: "handshake", emoji: "🤝", label: "Helpful" },
    { type: "trophy", emoji: "🏆", label: "Solution Accepted" },
];

export function mdToHtml(text) {
    if (!text) return "";
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>")
        .replace(/_(.*?)_/g, "<em>$1</em>")
        .replace(/^- (.+)$/gm, "<li>$1</li>")
        .replace(
            /(<li>.*<\/li>)/s,
            '<ul class="list-disc pl-4 space-y-0.5">$1</ul>',
        )
        .replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">$1</a>')
        .replace(/\n/g, "<br>");
}

export function getVideoEmbed(url) {
    try {
        const u = new URL(url);
        if (
            u.hostname.includes("youtube.com") ||
            u.hostname.includes("youtu.be")
        ) {
            const id = u.searchParams.get("v") || u.pathname.split("/").pop();
            return id ? `https://www.youtube.com/embed/${id}` : null;
        }
        if (u.hostname.includes("vimeo.com")) {
            const parts = u.pathname.split("/").filter(Boolean);
            const id = parts[0]; // numeric video ID
            const hash = parts[1]; // optional privacy hash
            if (!id) return null;
            return hash && /^[a-f0-9]+$/i.test(hash)
                ? `https://player.vimeo.com/video/${id}?h=${hash}`
                : `https://player.vimeo.com/video/${id}`;
        }
    } catch {}
    return null;
}

export function formatDate(dateStr) {
    if (!dateStr) return "";
    return new Date(dateStr).toLocaleDateString("en-PH", {
        month: "short",
        day: "numeric",
        year: "numeric",
    });
}

export function formatRelative(dateStr) {
    if (!dateStr) return "";
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return "just now";
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    const days = Math.floor(hrs / 24);
    if (days < 7) return `${days}d ago`;
    return formatDate(dateStr);
}
