// Wait for the page to load
document.addEventListener('DOMContentLoaded', function() {
    const video = document.getElementById('main-video');
    const likeBtn = document.getElementById('like-btn');
    const likeCountSpan = document.getElementById('like-count');
    const viewCountSpan = document.getElementById('view-count');

    let videoId = 1; // Assuming a static ID for this video for now

    // --- Functions to interact with backend --- //

    // Function to get initial stats (views and likes)
    function getStats() {
        // We will implement this with fetch() to call our PHP API
        console.log('Fetching initial stats...');
        // fetch(`api/get_stats.php?id=${videoId}`)
        //     .then(response => response.json())
        //     .then(data => {
        //         viewCountSpan.textContent = data.views;
        //         likeCountSpan.textContent = data.likes;
        //     });
    }

    // Function to increment view count
    function incrementView() {
        console.log('Incrementing view count...');
        // fetch(`api/update_view.php?id=${videoId}`, { method: 'POST' });
    }

    // Function to handle likes
    function handleLike() {
        console.log('Liking video...');
        // fetch(`api/update_like.php?id=${videoId}`, { method: 'POST' })
        //     .then(response => response.json())
        //     .then(data => {
        //         likeCountSpan.textContent = data.likes;
        //     });
    }

    // --- Event Listeners --- //

    // Increment view count when the video starts playing for the first time
    video.addEventListener('play', incrementView, { once: true });

    // Handle like button click
    likeBtn.addEventListener('click', handleLike);

    // --- Initial Load --- //
    // Get the initial stats when the page loads
    getStats();
});
