<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<style>
    .container {
        display: flex;
        height: 100vh;
        margin-top: 1.5rem;
    }
    .locations {
        width: 50%;
        padding: 20px;
        background-color: #f8f8f8;
        overflow-y: auto;
    }
    .location-card {
        padding: 10px;
        background: #fff;
        border: 1px solid #ddd;
        margin-bottom: 10px;
        cursor: pointer;
        display: flex;
        align-items: center;
    }
    .location-card.selected {
        border-color: #007bff; /* Border color when selected */
        background-color: #e7f0ff; /* Optional: Background color when selected */
    }
    .location-card input[type="checkbox"] {
        display: none; /* Hide the default checkbox */
    }
    #map {
        width: 50%;
        height: 100%;
    }
</style>

<div class="container">
   <div class="locations" id="locations"></div>
   <div id="map"></div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-plugins/layer/Marker.SlideTo.js"></script>

<script>
const carIcons = {};

async function loadCarModelImages() {
    try {
        const response = await fetch('https://aionindonesia.com/wp-json/wc/v3/products?consumer_key=ck_c9babaa0dc379d22bdcd1dcfbe4a109d300e8fb3&consumer_secret=cs_f565b5efb04a9cd3e21b1090e768583ffe6ef12e');
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        const data = await response.json();
        data.forEach(product => {
            const title = product.name;
            const images = product.images || [];
            if (title && images.length > 0) {
                carIcons[title] = images[0].src; // Simpan URL gambar pertama sebagai ikon
            }
        });

    } catch (error) {
        console.error('Error fetching car model images:', error);
    }
}

async function loadDealerships() {
    try {
        const response = await fetch('https://aionindonesia.com/wp-json/custom/v1/dealerships');
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const data = await response.json();
        const locationsContainer = document.getElementById('locations');
        locationsContainer.innerHTML = ''; // Clear existing content

        if (Array.isArray(data)) {
            data.forEach(dealership => {
                if (dealership.title && dealership.lat && dealership.lng && dealership.address) {
                    const card = document.createElement('div');
                    card.className = 'location-card';
                    card.dataset.lat = dealership.lat;
                    card.dataset.lng = dealership.lng;
                    card.innerHTML = `
                        <div data-dealership="${dealership.title}">
                              <b>${dealership.title}</b><br/>
                              <span>(${dealership.address})</span>
                        </div>
                    `;
                    locationsContainer.appendChild(card);
                }
            });
        } else {
            console.error('Invalid data format received from API');
        }
    } catch (error) {
        console.error('Error fetching dealership data:', error);
    }
}

function animateMarker(marker, toLatLng, duration) {
    var start = null;
    var fromLatLng = marker.getLatLng();

    function animate(timestamp) {
        if (!start) start = timestamp;
        var progress = timestamp - start;
        var progressRatio = Math.min(progress / duration, 1);

        var currentLat = fromLatLng.lat + (toLatLng.lat - fromLatLng.lat) * progressRatio;
        var currentLng = fromLatLng.lng + (toLatLng.lng - fromLatLng.lng) * progressRatio;

        marker.setLatLng([currentLat, currentLng]);

        if (progress < duration) {
            requestAnimationFrame(animate);
        } else {
            marker.setLatLng(toLatLng);
        }
    }

    requestAnimationFrame(animate);
}

function updateMarkerIcon(iconUrl) {
    if (marker) {
        marker.setIcon(L.icon({
            iconUrl: iconUrl,
            iconSize: [150, 80], 
            iconAnchor: [19, 38], 
            popupAnchor: [0, -38]
        }));
    }
}

var map = L.map('map').setView([-6.181388711133112, 106.9747525767128], 11);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap With Tokoweb.Co'
}).addTo(map);

// Initial marker setup with default icon
var marker = L.marker([-6.181388711133112, 106.9747525767128], {
    icon: L.icon({
        iconUrl: 'https://aionindonesia.com/wp-content/uploads/2024/08/deaker.svg', // Default icon
        iconSize: [150, 90], 
        iconAnchor: [19, 38], 
        popupAnchor: [0, -38]
    })
}).addTo(map);

document.getElementById('locations').addEventListener('click', function(e) {
    if (e.target && e.target.closest('.location-card')) {
        const selectedCard = e.target.closest('.location-card');
        
        // Ambil nilai dari atribut data-dealership
        const dealershipTitle = selectedCard.querySelector('[data-dealership]').getAttribute('data-dealership');
        console.log(dealershipTitle);
        
        // Deselect any previously selected card
        document.querySelectorAll('.location-card').forEach(card => {
            card.classList.remove('selected');
        });

        // Select the clicked card
        selectedCard.classList.add('selected');
        
        // Ambil lat dan lng
        var lat = parseFloat(selectedCard.dataset.lat);
        var lng = parseFloat(selectedCard.dataset.lng);
        var newLatLng = new L.LatLng(lat, lng);
        
        animateMarker(marker, newLatLng, 1000);
        map.panTo(newLatLng);

        // Update hidden input dengan nilai dari data-dealership
        const hiddenInput = document.querySelector('input[name="dealership"]');
        if (hiddenInput) {
            hiddenInput.value = dealershipTitle;
        }
    }
});

document.getElementById('car_model').addEventListener('change', function(e) {
    const selectedCarModel = e.target.value;
    console.log(selectedCarModel);
    const iconUrl = carIcons[selectedCarModel] || 'https://aionindonesia.com/wp-content/uploads/2024/08/deaker.svg'; // Ganti ikon sesuai pilihan
    updateMarkerIcon(iconUrl);
});

loadCarModelImages();
loadDealerships();
</script>
