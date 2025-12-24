//const width = window.innerWidth;
//const height = window.innerHeight;
//svg.attr("width", width).attr("height", height);
const svg = d3.select("#keywords-cluster");
const controls = d3.select("#controls");
const chatContainer = d3.select("#chat-container");
const input = d3.select("#user-input");
const sendButton = d3.select("#send-button");

const button = d3.select("#generate-button");
const responseContainer = d3.select("#response-container");
let globalWidth = 0;
let globalHeight = 0;

resizeSVG();
const clusters = [];
let particles = [];
let relationships = []; // Ajout des relations pondérées


// Appelle resizeSVG au chargement et redimensionnement de la fenêtre
window.addEventListener("resize", () => {
    resizeSVG();
    if (clusters.length > 0) {
        generateClustersFromKeywords(clusters.map(c => c.keyword)); // Re-générer avec les mots-clés actuels
    }
});
document.addEventListener("DOMContentLoaded", resizeSVG);
sendButton.on("click", sendMessage);
input.on("keypress", function(event) {
    if (event.key === "Enter") {
        sendMessage();
    }
});
input.on("keypress", function(event) {
    if (event.key === "Enter") {
        button.node().click();
    }
});

function fetchRelationships(rawKeywords) {
    return fetch("/api/relationships", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ keywords: rawKeywords })
    }).then(response => response.json());
}

function fetchGlossaryDefinition(keyword) {
    fetch("/api/glossary", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ term: keyword })
    })
        .then(response => response.json())
        .then(data => {
            if (typeof data.definition === "string" && typeof data.term === "string") {
                addChatMessage(`${data.term} : ${data.definition}`, "ai");
            } else if (data.error) {
                addChatMessage(data.error, "ai");
            } else {
                addChatMessage("Réponse invalide du glossaire.", "ai");
            }
        })
        .catch(error => {
            console.error("Erreur lors de la récupération du glossaire :", error);
            addChatMessage("Impossible de récupérer une définition pour ce terme.", "ai");
        });
}

// Charger les clusters
function loadClusters() {
    fetch("/api/explore_clusters")
        .then(response => response.json())
        .then(data => {
            console.log("Clusters reçus :", data);
            renderClustersVisualization(data);
        })
        .catch(error => {
            console.error("Erreur lors du chargement des clusters :", error);
        });
}

// Charger les statistiques
function loadStatistics() {
    fetch("/api/statistics")
        .then(response => response.json())
        .then(data => {
            console.log("Statistiques reçues :", data);
            renderStatistics(data);
        })
        .catch(error => {
            console.error("Erreur lors du chargement des statistiques :", error);
        });
}

function resizeSVG() {
    // Sélectionner tous les conteneurs de visualisation
    const containers = document.querySelectorAll("#keywords-cluster, #response-analysis");

    // Boucler à travers chaque conteneur
    containers.forEach(container => {
        const svg = container.querySelector("svg");
        if (svg) {
            const width = container.clientWidth;
            const height = container.clientHeight;
            svg.setAttribute("width", width);
            svg.setAttribute("height", height);
        }
    });

    // Mettre à jour les dimensions globales
    if (containers.length > 0) {
        const width = containers[0].clientWidth; // Prendre la largeur du premier container
        const height = containers[0].clientHeight; // Prendre la hauteur du premier container
        updateDimensions(width, height);
    }
}

function updateDimensions(newWidth, newHeight) {
    globalWidth = newWidth;
    globalHeight = newHeight;
}

// Tooltip simple
function showTooltip(text, x, y) {
    d3.select("body")
        .append("div")
        .attr("class", "tooltip")
        .style("position", "absolute")
        .style("left", `${x + 10}px`)
        .style("top", `${y + 10}px`)
        .style("background", "#333")
        .style("color", "#fff")
        .style("padding", "5px")
        .style("border-radius", "5px")
        .text(text);
}

function hideTooltip() {
    d3.select(".tooltip").remove();
}

function addClusterInteractions() {
    svg.selectAll(".cluster")
        .on("mouseover", function (event, d) {
            svg.selectAll(".weighted-link")
                .attr("stroke", link => (link.source === d.keyword || link.target === d.keyword) ? "yellow" : "blue")
                .attr("opacity", link => (link.source === d.keyword || link.target === d.keyword) ? 1 : 0.2);
        })
        .on("mouseout", function () {
            svg.selectAll(".weighted-link")
                .attr("stroke", "blue")
                .attr("opacity", d => d.weight);
        })
        .on("click", function (event, d) {
            const clickedKeyword = d.keyword;

            addChatMessage(clickedKeyword, "user"); // Affiche le mot-clé dans le chat

            // Analyse contextuelle pour le mot-clé cliqué
            analyzeContext(clickedKeyword); // Utilise la même logique que pour une question
        });
}

// Dessine les clusters
function drawClusters() {
    const clusterGroup = svg.selectAll(".cluster")
        .data(clusters)
        .enter()
        .append("g")
        .attr("class", "cluster");

    // Ajoute des cercles pour les clusters
    clusterGroup.append("circle")
        .attr("cx", d => d.x)
        .attr("cy", d => d.y)
        .attr("r", 0) // Commence à 0 pour l'animation
        .attr("fill", d => d.color)
        .attr("opacity", 0.7)
        .transition()
        .duration(1000)
        .attr("r", d => d.size);

    // Ajoute des étiquettes pour les clusters
    clusterGroup.append("text")
        .attr("x", d => d.x)
        .attr("y", d => d.y - d.size - 10)
        .text(d => d.keyword)
        .attr("text-anchor", "middle")
        .attr("fill", "white")
        .attr("opacity", 0)
        .transition()
        .duration(1000)
        .attr("opacity", 1);

    addClusterInteractions(); // Ajoute les interactions
}

// Dessine les particules
function drawParticles() {
    svg.selectAll(".particle")
        .data(particles)
        .enter()
        .append("circle")
        .attr("class", "particle")
        .attr("cx", d => d.x)
        .attr("cy", d => d.y)
        .attr("r", 0) // Commence à 0 pour l'animation
        .attr("fill", "lightblue")
        .attr("opacity", 0.8)
        .transition()
        .duration(1000)
        .attr("r", 5);
}

function calculateGroups() {
    const groups = [];
    const visited = new Set();

    // Parcourt les relations pour regrouper les clusters
    relationships.forEach(rel => {
        if (!visited.has(rel.source) || !visited.has(rel.target)) {
            const sourceCluster = clusters.find(c => c.keyword === rel.source);
            const targetCluster = clusters.find(c => c.keyword === rel.target);

            if (sourceCluster && targetCluster) {
                groups.push({
                    keywords: [rel.source, rel.target],
                    color: d3.interpolateWarm(Math.random()) // Couleur pour le groupe
                });
                visited.add(rel.source);
                visited.add(rel.target);
            }
        }
    });

    return groups;
}

// Dessine les zones de regroupement
function drawGroups(groups) {
    groups.forEach(group => {
        const xCoords = group.keywords.map(k => clusters.find(c => c.keyword === k)?.x || 0);
        const yCoords = group.keywords.map(k => clusters.find(c => c.keyword === k)?.y || 0);
        const xCenter = d3.mean(xCoords);
        const yCenter = d3.mean(yCoords);

        svg.append("circle")
            .attr("class", "group")
            .attr("cx", xCenter)
            .attr("cy", yCenter)
            .attr("r", Math.max(50, Math.min(100, xCoords.length * 30))) // Taille proportionnelle au nombre de mots-clés
            .attr("fill", group.color)
            .attr("opacity", 0.2);
    });
}

// Ajoute des lignes reliant les particules aux clusters
function addLines() {
    svg.selectAll(".line")
        .data(particles)
        .enter()
        .append("line")
        .attr("class", "line")
        .attr("x1", d => d.x)
        .attr("y1", d => d.y)
        .attr("x2", d => d.cluster.x)
        .attr("y2", d => d.cluster.y)
        .attr("stroke", "white")
        .attr("stroke-width", 1)
        .attr("opacity", 0.5);
}

// Ajoute les liens pondérés entre les clusters
function addWeightedLinks() {
    const keywordMap = new Map(clusters.map(d => [d.keyword, d]));

    svg.selectAll(".weighted-link")
        .data(relationships)
        .enter()
        .append("line")
        .attr("class", "weighted-link")
        .attr("stroke", "blue")
        .attr("stroke-width", d => d.weight * 5) // Épaisseur proportionnelle au poids
        .attr("opacity", d => d.weight) // Transparence proportionnelle au poids
        .attr("x1", d => keywordMap.get(d.source)?.x || 0)
        .attr("y1", d => keywordMap.get(d.source)?.y || 0)
        .attr("x2", d => keywordMap.get(d.target)?.x || 0)
        .attr("y2", d => keywordMap.get(d.target)?.y || 0);

    console.log("Relations récupérées :", relationships);

    // Calcul et affichage des groupes
    const groups = calculateGroups();
    drawGroups(groups);
}

// Animation des particules
function animateParticles() {

    particles.forEach(p => {
        p.x += p.vx;
        p.y += p.vy;

        /*

        // Retourne les particules dans la limite du canvas
        if (p.x < 50 || p.x > width - 50) p.vx *= -1;
        if (p.y < 50 || p.y > height - 50) p.vy *= -1;

        // Assure que les particules restent dans le canvas
        p.x = Math.max(50, Math.min(width - 50, p.x));
        p.y = Math.max(50, Math.min(height - 50, p.y));

         */
        // Retourne les particules dans la limite de leur cluster
        if (Math.abs(p.x - p.cluster.x) > 100) p.vx *= -1;
        if (Math.abs(p.y - p.cluster.y) > 100) p.vy *= -1;


    });

    // Met à jour la position des particules
    svg.selectAll(".particle")
        .data(particles)
        .attr("cx", d => d.x)
        .attr("cy", d => d.y);

    svg.selectAll(".line")
        .data(particles)
        .attr("x1", d => d.x)
        .attr("y1", d => d.y)
        .attr("x2", d => d.cluster.x)
        .attr("y2", d => d.cluster.y);

    // Met à jour les lignes pondérées (si besoin)
    /*
    svg.selectAll(".weighted-link")
        .data(relationships)
        .attr("x1", d => keywordMap.get(d.source)?.x || 0)
        .attr("y1", d => keywordMap.get(d.source)?.y || 0)
        .attr("x2", d => keywordMap.get(d.target)?.x || 0)
        .attr("y2", d => keywordMap.get(d.target)?.y || 0);

     */
}

// Ajouter une boucle d'animation
const timer = d3.timer(animateParticles);

setTimeout(() => {
    timer.stop(); // Arrête l'animation
    console.log("Animation arrêtée.");
}, 80000);

// Arrêter l'animation
function stopAnimation() {
    timer.stop(); // Arrête l'animation
}

// Génère des clusters et des particules
function generateClustersFromKeywords(rawKeywords) {
    clusters.length = 0;
    particles.length = 0;
    const svg = d3.select("#keywords-cluster");
    // Supprime les anciens éléments
    svg.selectAll("*").remove();

    if (rawKeywords.length === 0) {
        addChatMessage("Aucun mot-clé trouvé pour générer une nouvelle animation.", "ai");
        return;
    }

    // Appelle fetchRelationships pour obtenir les relations pondérées
    fetchRelationships(rawKeywords)
        .then(data => {
            relationships = data.relationships; // Relations entre les mots-clés

            // Enrichit les mots-clés pour D3.js
            const enrichedKeywords = rawKeywords.map((keyword, index) => ({
                keyword: keyword,
                x: Math.random() * (globalWidth - 100) + 50,
                y: Math.random() * (globalHeight - 100) + 50,
                size: Math.random() * 20 + 10,
                color: d3.interpolateWarm(index / rawKeywords.length),
            }));

            clusters.push(...enrichedKeywords);

            // Particules associées à chaque cluster
            enrichedKeywords.forEach(cluster => {
                for (let i = 0; i < 20; i++) {
                    particles.push({
                        cluster: cluster,
                        x: cluster.x + Math.random() * 100 - 50,
                        y: cluster.y + Math.random() * 100 - 50,
                        vx: Math.random() * 2 - 1,
                        vy: Math.random() * 2 - 1,
                    });
                }
            });

            drawClusters();
            drawParticles();
            addLines();
            if (relationships.length > 0) {
                addWeightedLinks();
            }
            // Calcule et dessine les groupes
            const groups = calculateGroups();
            drawGroups(groups);
        })
        .catch(error => {
            console.error("Erreur lors de la récupération des relations :", error);
        });
}

// Visualisation des clusters
function renderClustersVisualization(clusters) {
    const svg = d3.select("#cluster-visualization");
    svg.selectAll("*").remove();

    const nodes = [];
    const links = [];

    Object.entries(clusters).forEach(([cluster, keywords]) => {
        nodes.push({ id: cluster, group: 1 });
        keywords.forEach(keyword => {
            nodes.push({ id: keyword, group: 2 });
            links.push({ source: cluster, target: keyword });
        });
    });

    const simulation = d3.forceSimulation(nodes)
        .force("link", d3.forceLink(links).id(d => d.id).distance(100))
        .force("charge", d3.forceManyBody().strength(-300))
        .force("center", d3.forceCenter(500, 300));

    const link = svg.append("g")
        .selectAll("line")
        .data(links)
        .enter().append("line")
        .attr("stroke", "#999")
        .attr("stroke-width", 1.5);

    const node = svg.append("g")
        .selectAll("circle")
        .data(nodes)
        .enter().append("circle")
        .attr("r", 10)
        .attr("fill", d => d.group === 1 ? "blue" : "green")
        .call(d3.drag()
            .on("start", dragstarted)
            .on("drag", dragged)
            .on("end", dragended));

    simulation.on("tick", () => {
        link.attr("x1", d => d.source.x)
            .attr("y1", d => d.source.y)
            .attr("x2", d => d.target.x)
            .attr("y2", d => d.target.y);

        node.attr("cx", d => d.x)
            .attr("cy", d => d.y);
    });

    function dragstarted(event, d) {
        if (!event.active) simulation.alphaTarget(0.3).restart();
        d.fx = d.x;
        d.fy = d.y;
    }

    function dragged(event, d) {
        d.fx = event.x;
        d.fy = event.y;
    }

    function dragended(event, d) {
        if (!event.active) simulation.alphaTarget(0);
        d.fx = null;
        d.fy = null;
    }
}

function renderResponseVisualization(keywords, intent, response)  {
    if (!keywords || !intent || !response) {
        console.error("Données manquantes pour la visualisation", { keywords, intent, response });
        return; // Arrêtez l'exécution si les données sont manquantes
    }
    const svg = d3.select("#response-analysis");
    svg.selectAll("*").remove(); // Supprime les anciens éléments

    // Vérifier si SVG est bien sélectionné
    if (svg.empty()) {
        console.error("L'élément SVG n'a pas pu être trouvé ou créé.");
        return;
    }

    const width = +svg.attr("width") || 500; // Définir une largeur par défaut si non spécifiée
    const height = +svg.attr("height") || 500; // Définir une hauteur par défaut si non spécifiée


    const nodes = [
        { id: "Question", group: 1 },
        ...keywords.map((kw, i) => ({ id: kw, group: 2 })),
        { id: `Intent: ${intent}`, group: 3 },
        { id: `Réponse: ${response}`, group: 4 }
    ];

    const links = [
        ...keywords.map(kw => ({ source: "Question", target: kw })),
        ...keywords.map(kw => ({ source: kw, target: `Intent: ${intent}` })),
        { source: `Intent: ${intent}`, target: `Réponse: ${response}` }
    ];

    // Création de la simulation de force
    const simulation = d3.forceSimulation(nodes)
        .force("link", d3.forceLink(links).id(d => d.id).distance(100))
        .force("charge", d3.forceManyBody().strength(-300))
        .force("center", d3.forceCenter(width / 2, height / 2));

    // Création des liens (avec vérifications)
    const link = svg.append("g")
        .selectAll("line")
        .data(links)
        .enter()
        .append("line")
        .attr("stroke", link => (link.source === "Intent" ? "orange" : "#999"))
        .attr("stroke-width", d => d.weight || 1.5);

    // Création des nœuds (avec vérifications)
    const node = svg.append("g")
        .selectAll("circle")
        .data(nodes)
        .enter()
        .append("circle")
        .attr("r", 10)
        .attr("fill", d => d3.schemeCategory10[d.group])
        .on("mouseover", (event, d) => {
            if (d) {
                showTooltip(d.id, event.pageX, event.pageY); // Affiche le tooltip
            }
        })
        .on("mouseout", () => {
            hideTooltip(); // Cache le tooltip
        })
        .on("click", (event, d) => {
            if (d && d.group === 2) {
                fetchGlossaryDefinition(d.id); // Affiche la définition du mot-clé
            } else if (d) {
                addChatMessage(`Vous avez cliqué sur : ${d.id}`, "ai");
            }
        });

    node.append("title").text(d => d.id);

    node.transition()
        .duration(500)
        .attr("r", 10)
        .attr("opacity", 1);

    simulation.on("tick", () => {
        link
            .attr("x1", d => d.source.x)
            .attr("y1", d => d.source.y)
            .attr("x2", d => d.target.x)
            .attr("y2", d => d.target.y);

        node
            .attr("cx", d => d.x)
            .attr("cy", d => d.y);
    });
}

// Visualisation des statistiques
function renderStatistics(data) {
    const intents = Object.entries(data.intents);
    const keywords = Object.entries(data.keywords);

    const svg = d3.select("#statistics-visualization");
    svg.selectAll("*").remove();

    const width = 600;
    const height = 300;
    const margin = { top: 20, right: 20, bottom: 30, left: 40 };

    const x = d3.scaleBand()
        .domain(intents.map(d => d[0]))
        .range([margin.left, width - margin.right])
        .padding(0.1);

    const y = d3.scaleLinear()
        .domain([0, d3.max(intents, d => d[1])])
        .nice()
        .range([height - margin.bottom, margin.top]);

    const xAxis = g => g
        .attr("transform", `translate(0,${height - margin.bottom})`)
        .call(d3.axisBottom(x).tickSizeOuter(0));

    const yAxis = g => g
        .attr("transform", `translate(${margin.left},0)`)
        .call(d3.axisLeft(y).ticks(null, "s"));

    svg.append("g")
        .selectAll("rect")
        .data(intents)
        .enter().append("rect")
        .attr("x", d => x(d[0]))
        .attr("y", d => y(d[1]))
        .attr("height", d => y(0) - y(d[1]))
        .attr("width", x.bandwidth())
        .attr("fill", "steelblue");

    svg.append("g").call(xAxis);
    svg.append("g").call(yAxis);
}

function addChatMessage(message, sender) {
    chatContainer.append("div")
        .attr("class", `chat-message ${sender}`)
        .append("div")
        .attr("class", `chat-bubble ${sender}`)
        .text(message);

    // Scroll automatique vers le bas
    chatContainer.node().scrollTop = chatContainer.node().scrollHeight;
}

function analyzeContext(question) {
    console.log(question)
    fetch("/api/analyze_context", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ message: question })
    })
        .then(response => response.json())
        .then(data => {
            console.log("Données reçues:", data);
            console.log("Mots-clés reçus :", data.keywords); // Ajoutez ceci pour vérifier les données reçues
            if (data.response) {
                const keywords = data.keywords || [];
                const intent = data.intent || "unknown";
                const context = data.context || "unknown";

                // Affiche la réponse dans le chat
                addChatMessage(`Réponse (${context}) : ${data.response}`, "ai");
                // Visualiser les clusters et statistiques si des mots-clés sont détectés
                if (keywords.length > 0) {
                    generateClustersFromKeywords(keywords);
                    loadStatistics(); // Charger les statistiques associées
                }
                renderResponseVisualization(keywords, intent, data.response);
            } else if (data.error) {
                addChatMessage(data.error, "ai");
            }
        })
        .catch(error => {
            console.error("Erreur lors de l'analyse contextuelle :", error);
            addChatMessage("Impossible d'analyser cette question.", "ai");
        });
}
// Charger les clusters manuellement via un bouton
function setupManualControls() {
    const loadClustersButton = document.getElementById("load-clusters-button");
    const loadStatisticsButton = document.getElementById("load-statistics-button");

    if (loadClustersButton) {
        loadClustersButton.addEventListener("click", () => {
            loadClusters();
        });
    }

    if (loadStatisticsButton) {
        loadStatisticsButton.addEventListener("click", () => {
            loadStatistics();
        });
    }
}

// Appeler setupManualControls lors de l'initialisation
document.addEventListener("DOMContentLoaded", () => {
    setupManualControls();
    resizeSVG(); // Ajuster les dimensions initiales
});


function sendMessage() {
    const question = input.property("value").trim();
    if (question !== "") {
        addChatMessage(question, "user");

        // Vérifie si la question est un mot-clé isolé
        if (question.split(" ").length === 1) {
            fetchGlossaryDefinition(question);
            input.property("value", "");
            return;
        }

        // Analyse contextuelle pour les questions complexes
        analyzeContext(question); // Déclenche l'analyse contextuelle

        input.property("value", ""); // Réinitialise l'entrée utilisateur
    }
}



