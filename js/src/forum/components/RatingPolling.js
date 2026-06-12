import app from 'flarum/forum/app';

class RatingPolling {
    constructor() {
        this.interval = null;
        this.discussionId = null;
        this.paused = false;
    }

    start(discussion) {
        this.stop();
        this.discussionId = discussion.id();
        this.paused = false;

        this.interval = setInterval(() => {
            this.poll(discussion);
        }, 8000);
    }

    stop() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
        this.discussionId = null;
        this.paused = false;
    }

    // Temporarily hold the page-level poll while the ratings modal is open: the
    // modal runs its own (faster) poll and refreshes the discussion, so polling
    // twice for the same data is wasted requests.
    pause() {
        this.paused = true;
    }

    resume() {
        this.paused = false;
    }

    poll(discussion) {
        if (this.paused) return;

        // Skip the request while the tab is in the background. Readers leave
        // discussion tabs open, and a fixed-interval poll on every hidden tab is
        // pure server load for data nobody is looking at. The interval keeps
        // ticking (a cheap no-op) and resumes on the next tick once visible.
        if (typeof document !== 'undefined' && document.hidden) return;

        if (!discussion || discussion.id() !== this.discussionId) {
            this.stop();
            return;
        }

        app.request({
            method: 'GET',
            url: app.forum.attribute('apiUrl') + '/discussion-ratings/poll',
            params: {
                discussion_id: discussion.id(),
            },
            // Background poll: never alert on failure (that would pop an error
            // every interval), but surface genuine server errors (5xx) to the
            // console so they stay diagnosable. 401/403/404 are expected/ignorable
            // in a polling context (e.g. session expiry, discussion gone).
            errorHandler: (e) => {
                if (e && e.status >= 500 && typeof console !== 'undefined') {
                    console.warn('[topic-rating] rating poll failed (status ' + e.status + ')');
                }
            },
        }).then((data) => {
            const oldAvg = discussion.ratingAverage();
            const oldCount = discussion.ratingCount();

            if (data.ratingAverage !== oldAvg || data.ratingCount !== oldCount) {
                discussion.pushData({
                    attributes: {
                        ratingAverage: data.ratingAverage,
                        ratingCount: data.ratingCount,
                        lastRatedAt: data.lastRatedAt,
                        ratingDisabled: data.ratingDisabled,
                        userRating: data.userRating !== undefined ? data.userRating : discussion.userRating(),
                    },
                });
                m.redraw();
            }
        }).catch(() => {});
    }
}

const ratingPolling = new RatingPolling();
export default ratingPolling;
