document.addEventListener("DOMContentLoaded", () => {
  const CSRF_TOKEN = document.body.getAttribute('data-csrf-token');

  const audio = document.getElementById('audio');
  const playBtn = document.getElementById('playBtn');
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  const shuffleBtn = document.getElementById('shuffleBtn');
  const repeatBtn = document.getElementById('repeatBtn');
  const volumeControl = document.getElementById('volume');
  const currentTimeDisplay = document.getElementById('current-time');
  const durationDisplay = document.getElementById('duration');
  const visualizer = document.getElementById('visualizer');
  const progressBar = document.getElementById('progress-bar');
  const statusDisplay = document.getElementById('status');
  const bitrateDisplay = document.getElementById('bitrate');
  const currentYear = document.getElementById('current-year');
  const currentSongDisplay = document.getElementById('current-song');
  const currentArtistDisplay = document.getElementById('current-artist');
  const coverArt = document.getElementById('coverArt');
  const playlistElement = document.getElementById('playlist');
  const refreshBtn = document.getElementById('refreshBtn');
  const uploadBtn = document.getElementById('uploadBtn');
  const uploadModal = document.getElementById('uploadModal');
  const cancelBtn = document.getElementById('cancelBtn');
  const cancelUploadBtn = document.getElementById('cancelUploadBtn');
  const uploadForm = document.getElementById('uploadForm');
  const coverInput = document.getElementById('coverInput');
  const coverFileName = document.getElementById('coverFileName');
  const coverPreview = document.getElementById('coverPreview');
  const coverPreviewImg = document.getElementById('coverPreviewImg');
  const songInput = document.getElementById('songInput');
  const songFileName = document.getElementById('songFileName');
  const toast = document.getElementById('toast');
  const miniPlayer = document.getElementById('miniPlayer');

  // Edit Song Modal Elements
  const editSongModal = document.getElementById('editSongModal');
  const editSongForm = document.getElementById('editSongForm');
  const editSongId = document.getElementById('editSongId');
  const editSongTitle = document.getElementById('editSongTitle');
  const editSongArtist = document.getElementById('editSongArtist');
  const editSongLyrics = document.getElementById('editSongLyrics');
  const cancelEditSongBtn = document.getElementById('cancelEditSongBtn'); // Top X
  const closeEditSongModalBtn = document.getElementById('closeEditSongModalBtn'); // Bottom cancel button

  let playlist = [];
  let currentSongIndex = -1;
  let isPlaying = false;
  let isShuffled = false;
  let isRepeatOn = false;
  let originalPlaylistOrder = []; // Stores the order for unshuffling
  let fullPlaylistData = [];    // Always stores the complete list from server for filtering
  let audioContext, analyser, dataArray;
  let imageObserver; // For lazy loading

  currentYear.textContent = new Date().getFullYear();

  // Debounce utility function
  function debounce(func, delay) {
      let timeoutId;
      return function(...args) {
          clearTimeout(timeoutId);
          timeoutId = setTimeout(() => {
              func.apply(this, args);
          }, delay);
      };
  }

  // Example of how debounce would be used later (not implementing search now):
  // const searchInput = document.getElementById('searchInput');
  const searchInput = document.getElementById('searchInput');
  if (searchInput) {
    searchInput.addEventListener('input', debounce(event => {
      const searchTerm = event.target.value.trim().toLowerCase();
      filterPlaylist(searchTerm);
    }, 300));
  }

  // Utility to escape HTML for safe rendering of user input
  function escapeHtml(unsafe) {
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
  }

  const barCount = 50;
  for (let i = 0; i < barCount; i++) {
    const bar = document.createElement('div');
    bar.className = 'bar';
    bar.style.height = '10px';
    visualizer.appendChild(bar);
  }
  const bars = document.querySelectorAll('.bar');

  function formatTime(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);
    return [h.toString().padStart(2, '0'), m.toString().padStart(2, '0'), s.toString().padStart(2, '0')].join(':');
  }

  function updateTimeDisplays() {
    currentTimeDisplay.textContent = formatTime(audio.currentTime);
    if (audio.duration) {
      durationDisplay.textContent = formatTime(audio.duration);
      const progress = (audio.currentTime / audio.duration) * 100;
      progressBar.style.width = `${progress}%`;
    }
  }

  function setupAudioContext() {
    try {
      audioContext = new (window.AudioContext || window.webkitAudioContext)();
      analyser = audioContext.createAnalyser();
      analyser.fftSize = 256;
      const source = audioContext.createMediaElementSource(audio);
      source.connect(analyser);
      analyser.connect(audioContext.destination);
      dataArray = new Uint8Array(analyser.frequencyBinCount);
      statusDisplay.textContent = "Playing";
      updateVisualizer();
    } catch (e) {
      console.error("AudioContext setup error:", e);
      statusDisplay.textContent = "Error initializing audio playback: " + e.message;
      showNotification("Audio system error. Please refresh.", true);
    }
  }

  function updateVisualizer() {
    if (!analyser) return;
    analyser.getByteFrequencyData(dataArray);
    const segmentSize = Math.floor(dataArray.length / barCount);
    for (let i = 0; i < barCount; i++) {
      let sum = 0;
      for (let j = 0; j < segmentSize; j++) {
        sum += dataArray[i * segmentSize + j];
      }
      const average = sum / segmentSize;
      bars[i].style.height = `${average / 2}px`;
    }
    requestAnimationFrame(updateVisualizer);
  }

  async function fetchPlaylist() {
    playlistElement.innerHTML = `
      <li class="text-center py-10">
        <div class="spinner mx-auto mb-2"></div>
        <p>Loading playlist...</p>
      </li>
    `;
    try {
      const response = await fetch('/?action=getPlaylist'); // Adjusted URL
      if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`Network response was not ok: ${response.status} ${response.statusText} - ${errorText}`);
      }
      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
          const responseText = await response.text();
          console.error("Received non-JSON response:", responseText);
          throw new TypeError("Oops, we haven't got JSON! Response: " + responseText.substring(0, 100) + "...");
      }
      const responseData = await response.json();

      if (responseData.status === 'success' && responseData.data && Array.isArray(responseData.data.songs)) {
        fullPlaylistData = [...responseData.data.songs]; // Store the full list
        playlist = [...fullPlaylistData]; // Initially, displayed playlist is the full one
        originalPlaylistOrder = [...fullPlaylistData];
        updatePlaylistDisplay();
        showNotification("Playlist loaded successfully!");
      } else if (responseData.status === 'error' && responseData.message) {
        console.error("API error fetching playlist:", responseData.message);
        playlistElement.innerHTML = `<li class="text-center py-10 text-red-400"><i class="fas fa-exclamation-triangle text-3xl mb-2"></i><p>Error loading playlist: ${responseData.message}</p></li>`;
        showNotification(`Error: ${responseData.message}`, true);
      } else {
        console.error("Invalid or unexpected JSON structure from getPlaylist:", responseData);
        playlistElement.innerHTML = `<li class="text-center py-10 text-red-400"><i class="fas fa-exclamation-triangle text-3xl mb-2"></i><p>Failed to load playlist. Unexpected server response.</p></li>`;
        showNotification("Failed to load playlist: Unexpected response", true);
      }
    } catch (error) {
      console.error("Critical error fetching playlist:", error);
      playlistElement.innerHTML = `<li class="text-center py-10 text-red-400"><i class="fas fa-exclamation-triangle text-3xl mb-2"></i><p>Error loading playlist. Please check connection and try again.</p></li>`;
      showNotification("Network error or critical issue loading playlist.", true);
    }
  }

  function updatePlaylistDisplay() {
    if (playlist.length === 0) {
        const searchInputVal = searchInput ? searchInput.value.trim() : "";
        if (searchInputVal) {
            playlistElement.innerHTML = `
                <li class="text-center py-10 text-white/50">
                    <i class="fas fa-search text-3xl mb-2"></i>
                    <p>No songs match your search for "${escapeHtml(searchInputVal)}".</p>
                </li>`;
        } else {
            playlistElement.innerHTML = `
                <li class="text-center py-10 text-white/50">
                    <i class="fas fa-music text-3xl mb-2"></i>
                    <p>No songs in playlist.</p>
                </li>`;
        }
        initializeImageObserver(); // Still call observer to clear previous ones if any
        return;
    }
    playlistElement.innerHTML = playlist.map((song, index) => `
      <li class="song-item bg-white/5 p-3 rounded-lg hover:bg-white/10 transition-all ${currentSongIndex === index ? 'current-song' : ''}">
        <div class="flex items-center gap-3">
          <div class="flex-shrink-0 w-10 h-10 rounded-md overflow-hidden ${song.cover ? '' : 'default-cover'}" ${!song.cover ? 'aria-label="Default cover art icon"' : ''} onclick="playSong(${index})">
            ${song.cover ?
              `<img data-src="/${song.cover}" class="lazy-load-cover w-full h-full object-cover" alt="Cover art for ${song.title || 'song'}" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7">` :
              `<i class="fas fa-music w-full h-full flex items-center justify-center" aria-hidden="true"></i>`}
          </div>
          <div class="flex-1 min-w-0" onclick="playSong(${index})">
            <p class="font-medium truncate">${song.title}</p>
            <p class="text-sm text-white/70 truncate">${song.artist}</p>
          </div>
          <span class="text-xs text-white/50 flex-shrink-0" onclick="playSong(${index})">${formatTime(song.duration || 0)}</span>
          <button class="edit-song-btn text-gray-400 hover:text-blue-400 p-2 rounded-full text-xs ml-auto flex-shrink-0"
                  data-song-id="${song.id}"
                  aria-label="Edit ${escapeHtml(song.title)}">
              <i class="fas fa-pencil-alt"></i>
          </button>
        </div>
      </li>
    `).join('');
  }

  function toggleShuffle() {
    const searchInputVal = searchInput ? searchInput.value.trim() : "";
    if (searchInputVal !== '') {
        searchInput.value = ''; // Clear search
        playlist = [...fullPlaylistData]; // Reset to full playlist before shuffling
        showNotification("Search cleared, shuffling full playlist.");
    }
    // originalPlaylistOrder should always refer to the full, unshuffled list's original server order
    // If playlist was filtered, we reset it above. If not, playlist is already fullPlaylistData or a shuffled version of it.

    isShuffled = !isShuffled;
    if (isShuffled) {
        // Always shuffle from a clean, full list if we want 'originalPlaylistOrder' to be meaningful for un-shuffling
        // If playlist is already fullPlaylistData, this is fine.
        // If playlist was a previously shuffled version, we need to ensure we're not re-shuffling a shuffle.
        // Simplest: if turning shuffle ON, always start from fullPlaylistData's current order (which is originalPlaylistOrder if not already shuffled)
        if(playlist !== originalPlaylistOrder && originalPlaylistOrder.length > 0){ // if playlist is already shuffled or filtered.
             playlist = [...originalPlaylistOrder]; // Revert to original order of full list before shuffle
        }

        let playingSongId = null;
        if (isPlaying && currentSongIndex !== -1 && currentSongIndex < playlist.length) {
            playingSongId = playlist[currentSongIndex].id;
        }

        // Fisher-Yates shuffle on the 'playlist' array
        for (let i = playlist.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [playlist[i], playlist[j]] = [playlist[j], playlist[i]];
        }

        if (playingSongId !== null) {
            currentSongIndex = playlist.findIndex(song => song.id === playingSongId);
        } else {
            currentSongIndex = -1; // Or 0 if you want it to pick a new first song
        }

        shuffleBtn.classList.add('control-active');
        showNotification("Shuffle mode on");
    } else {
        // Unshuffle: revert to originalPlaylistOrder, maintaining current song if possible
        let playingSongId = null;
        if (isPlaying && currentSongIndex !== -1 && currentSongIndex < playlist.length) {
            playingSongId = playlist[currentSongIndex].id;
        }
        playlist = [...originalPlaylistOrder]; // originalPlaylistOrder always has the server order
        if (playingSongId !== null) {
            currentSongIndex = playlist.findIndex(song => song.id === playingSongId);
        } else {
            currentSongIndex = -1;
        }
        shuffleBtn.classList.remove('control-active');
        showNotification("Shuffle mode off");
    }
    updatePlaylistDisplay(); // This will call initializeImageObserver
  }

  function toggleRepeat() {
    isRepeatOn = !isRepeatOn;
    repeatBtn.classList.toggle('control-active', isRepeatOn);
    showNotification(`Repeat mode ${isRepeatOn ? 'on' : 'off'}`);
  }

  async function playSong(index) {
    if (index < 0 || index >= playlist.length) return;
    currentSongIndex = index;
    const song = playlist[index];
    currentSongDisplay.textContent = song.title;
    currentArtistDisplay.textContent = song.artist;
    if (song.title.length > 20) {
      currentSongDisplay.innerHTML = `<span class="marquee">${song.title}</span>`;
    } else {
      currentSongDisplay.textContent = song.title;
    }
    if (song.cover) {
      coverArt.innerHTML = `<img src="/${song.cover}" class="w-full h-full object-cover" alt="Cover art for ${song.title}">`; // Adjusted path for cover
      coverArt.removeAttribute('aria-label');
    } else {
      coverArt.innerHTML = `<i class="fas fa-music text-xl text-purple-400" aria-hidden="true"></i>`;
      coverArt.setAttribute('aria-label', 'Default cover art icon');
    }
    audio.src = `/${song.file}`; // Adjusted path for song file
    audio.load();
    try {
      await audio.play();
      playBtn.innerHTML = '<i class="fas fa-pause text-xl"></i>';
      playBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
      playBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
      isPlaying = true;
      statusDisplay.textContent = "Playing";
      miniPlayer.innerHTML = '<i class="fas fa-pause"></i>';
      miniPlayer.classList.remove('hidden');
      if (!audioContext) {
        setupAudioContext();
      }
      showNotification(`Now playing: ${song.title}`);
      setupMediaSession(song);
    } catch (error) {
      console.error("Error playing song:", song.title, error);
      showNotification(`Error playing ${song.title}: ${error.message}`, true);
      statusDisplay.textContent = "Error playing song";
    }
  }

  async function togglePlayPause() {
    if (playlist.length === 0) {
      showNotification('No songs in playlist', true);
      return;
    }
    if (currentSongIndex === -1) {
      await playSong(0);
      return;
    }
    if (audio.paused) {
      try {
        await audio.play();
        playBtn.innerHTML = '<i class="fas fa-pause text-xl"></i>';
        playBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
        playBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
        isPlaying = true;
        statusDisplay.textContent = "Playing";
        miniPlayer.innerHTML = '<i class="fas fa-pause"></i>';
        if (!audioContext) {
          setupAudioContext();
        }
      } catch (error) {
        console.error("Play error:", error);
        showNotification('Click anywhere to play', true);
      }
    } else {
      audio.pause();
      playBtn.innerHTML = '<i class="fas fa-play text-xl"></i>';
      playBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
      playBtn.classList.add('bg-green-600', 'hover:bg-green-700');
      isPlaying = false;
      statusDisplay.textContent = "Paused";
      miniPlayer.innerHTML = '<i class="fas fa-play"></i>';
    }
  }

  function prevSong() {
    if (playlist.length === 0) return;
    let newIndex = currentSongIndex - 1;
    if (newIndex < 0) newIndex = playlist.length - 1;
    playSong(newIndex);
  }

  function nextSong() {
    if (playlist.length === 0) return;
    if (isRepeatOn) {
      audio.currentTime = 0;
      audio.play().catch(error => { // Add catch here too
         console.error("Error re-playing song:", error);
         showNotification(`Error playing: ${error.message}`, true);
      });
      return;
    }
    let newIndex = currentSongIndex + 1;
    if (newIndex >= playlist.length) newIndex = 0;
    playSong(newIndex);
  }

  function showNotification(message, isError = false) {
    toast.textContent = message;
    // Ensure correct classes are applied for color based on error or success
    toast.className = 'fixed bottom-16 left-1/2 transform -translate-x-1/2 p-4 rounded-lg text-white z-50 transition-all duration-300 ease-in-out';
    if (isError) {
      toast.classList.add('bg-red-500');
    } else {
      toast.classList.add('bg-green-500');
    }
    toast.classList.add('show'); // Make it visible

    clearTimeout(toast.timeoutId);
    toast.timeoutId = setTimeout(() => {
      toast.classList.remove('show');
      // Reset classes after hiding to ensure correct color next time
      toast.className = 'fixed bottom-16 left-1/2 transform -translate-x-1/2 p-4 rounded-lg text-white z-50 transition-all duration-300 ease-in-out';
    }, 3000);
  }

  playBtn.addEventListener('click', togglePlayPause);
  prevBtn.addEventListener('click', prevSong);
  nextBtn.addEventListener('click', nextSong);
  shuffleBtn.addEventListener('click', toggleShuffle);
  repeatBtn.addEventListener('click', toggleRepeat);

  volumeControl.addEventListener('input', () => {
    audio.volume = volumeControl.value;
  });

  progressBar.parentElement.addEventListener('click', (e) => {
    if (!audio.duration) return;
    const rect = progressBar.parentElement.getBoundingClientRect(); // Use parent for rect
    const pos = (e.clientX - rect.left) / rect.width;
    audio.currentTime = pos * audio.duration;
  });

  audio.addEventListener('loadedmetadata', updateTimeDisplays);
  audio.addEventListener('timeupdate', updateTimeDisplays);
  audio.addEventListener('ended', () => {
    if (isRepeatOn) {
      audio.currentTime = 0;
      audio.play().catch(error => {
         console.error("Error re-playing song on end:", error);
         showNotification(`Error playing: ${error.message}`, true);
      });
    } else {
      nextSong();
    }
  });
  audio.addEventListener('error', (e) => {
    console.error("Audio Error:", e);
    statusDisplay.textContent = "Error loading audio";
    showNotification("Error loading audio file. It might be corrupt or unsupported.", true);
  });

  refreshBtn.addEventListener('click', async () => {
    refreshBtn.innerHTML = '<i class="fas fa-sync-alt animate-spin"></i>';
    await fetchPlaylist();
    refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
  });

  uploadBtn.addEventListener('click', () => {
    if (uploadModal) uploadModal.style.display = 'flex';
  });
  cancelBtn.addEventListener('click', () => {
    if (uploadModal) uploadModal.style.display = 'none';
  });
  cancelUploadBtn.addEventListener('click', () => {
    if (uploadModal) uploadModal.style.display = 'none';
  });

  coverInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;
    coverFileName.textContent = file.name;
    coverPreview.classList.remove('hidden');
    const reader = new FileReader();
    reader.onload = (event) => {
      coverPreviewImg.src = event.target.result;
    };
    reader.readAsDataURL(file);
  });

  songInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;
    songFileName.textContent = file.name;
  });

  uploadForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<div class="spinner mr-2"></div> Uploading...';
    submitBtn.disabled = true;
    const formData = new FormData(e.target);
    if (CSRF_TOKEN) {
        formData.append('csrf_token', CSRF_TOKEN);
    } else {
        console.error('CSRF_TOKEN is not available from body data attribute.');
        showNotification('A required security token is missing. Please refresh the page and ensure JavaScript can access it.', true);
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        return;
    }

    try {
      const response = await fetch('/?action=uploadSong', { // Adjusted URL
        method: 'POST',
        body: formData
      });
      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
          const responseText = await response.text();
          console.error("Received non-JSON response from upload:", responseText);
          throw new TypeError("Oops, we haven't got JSON from upload! Response: " + responseText.substring(0,100) + "...");
      }
      const result = await response.json();

      if (result.status === 'success') {
        await fetchPlaylist();
        if (uploadModal) uploadModal.style.display = 'none';
        uploadForm.reset();
        coverPreview.classList.add('hidden');
        coverPreviewImg.src = '';
        coverFileName.textContent = 'Choose cover image';
        songFileName.textContent = 'Choose MP3 file';
        showNotification(result.message || 'Song uploaded successfully!');
      } else {
        showNotification(result.message || 'Upload failed. Please try again.', true);
      }
    } catch (error) {
      console.error("Upload error:", error);
      showNotification('Upload failed: Network error or server issue. ' + error.message, true);
    } finally {
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
    }
  });

  function setupMediaSession(song) {
    if ('mediaSession' in navigator) {
      navigator.mediaSession.metadata = new MediaMetadata({
        title: song.title,
        artist: song.artist,
        album: 'Local Radio', // You can customize this
        artwork: song.cover ? [{ src: `/${song.cover}` }] : [] // Adjusted path for cover
      });

      navigator.mediaSession.setActionHandler('play', togglePlayPause);
      navigator.mediaSession.setActionHandler('pause', togglePlayPause);
      navigator.mediaSession.setActionHandler('previoustrack', prevSong);
      navigator.mediaSession.setActionHandler('nexttrack', nextSong);
      // Add more handlers as needed: seekbackward, seekforward, etc.
    }
  }

  miniPlayer.addEventListener('click', () => {
    // Attempt to find the main player container to scroll to.
    // This assumes the player container is identifiable, e.g., by being the parent of #audio
    const playerContainer = document.querySelector('.w-full.max-w-2xl.bg-gray-800');
    if (playerContainer) {
        playerContainer.scrollIntoView({ behavior: 'smooth' });
    }
  });

  fetchPlaylist(); // Initial fetch

  // Function to initialize or re-initialize IntersectionObserver
  function initializeImageObserver() {
    if (imageObserver) {
      imageObserver.disconnect();
    }

    const lazyImages = document.querySelectorAll('img.lazy-load-cover');
    if (!lazyImages.length) return;

    imageObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const img = entry.target;
          const src = img.getAttribute('data-src');
          if (src) {
            img.setAttribute('src', src);
            img.classList.remove('lazy-load-cover');
            img.onload = () => {
              img.classList.add('loaded');
            };
          }
          observer.unobserve(img);
        }
      });
    }, {
      rootMargin: '0px 0px 100px 0px' // Start loading 100px before they enter viewport
    });

    lazyImages.forEach(img => {
      imageObserver.observe(img);
    });
  }

  // Ensure fetchPlaylist also calls initializeImageObserver on success (it does via updatePlaylistDisplay)

  function filterPlaylist(searchTerm) {
    if (!searchTerm) {
        playlist = [...fullPlaylistData];
    } else {
        playlist = fullPlaylistData.filter(song => {
            const titleMatch = song.title.toLowerCase().includes(searchTerm);
            const artistMatch = song.artist.toLowerCase().includes(searchTerm);
            return titleMatch || artistMatch;
        });
    }
    // If currently playing a song not in the filtered list, what happens?
    // For simplicity, playback might stop or continue but song won't be highlighted.
    // Or, find new index of current song if it exists in filtered list.
    if (isPlaying && currentSongIndex !== -1) {
        const currentPlayingSongId = (isShuffled ? originalPlaylistOrder : fullPlaylistData)[currentSongIndex]?.id;
        // Above line is tricky if originalPlaylistOrder was shuffled. Let's use a safer way:
        // Let's assume currentSongIndex always refers to the `playlist` array.
        // When playlist is re-filtered, the currentSongIndex might become invalid or point to a different song.
        // Simplest approach: if current song is not in new filtered `playlist`, reset currentSongIndex.
        // This might stop playback or cause next/prev to behave from start of filtered list.
        // A more robust solution would be to try and keep the current song playing if it's in the filter.

        // For now, just re-render. User will click to play again if needed.
        // currentSongIndex = -1; // Optionally reset, or try to find it:
        const currentlyPlayingSong = (currentSongIndex >=0 && currentSongIndex < (isShuffled ? playlist : originalPlaylistOrder).length) ? (isShuffled ? playlist : originalPlaylistOrder)[currentSongIndex] : null;
        if(currentlyPlayingSong){
            const newIdx = playlist.findIndex(s => s.id === currentlyPlayingSong.id);
            currentSongIndex = newIdx; // Will be -1 if not found, which is fine.
        } else {
            currentSongIndex = -1;
        }

    }
    updatePlaylistDisplay(); // This will call initializeImageObserver
  }

  window.playSong = playSong; // Make it globally accessible if needed for inline onclick attributes
});
