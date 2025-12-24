
import {Application, Container, Graphics, Sprite, Text, Texture} from 'pixi.js';

console.log('lancement app2.js')

const app = new Application();
await app.init({ resizeTo: window });
/*
app.init({
   // view: document.getElementById("pixi-canvas"),
    width: width,
    height: height,
    backgroundColor: 0x101020,
    forceCanvas: true, // Activer le mode Canvas en cas d'échec WebGL
});

 */

document.body.appendChild(app.canvas);

// Add a container to center our sprite stack on the page
const container = new Container();

const width = app.screen.width;
const height = app.screen.height;

console.log(width,height);

app.stage.addChild(container);

/*
const background = new Graphics();
background.fill('green');
background.rect(0, 0, container.width, container.height);
container.addChild(background);
console.log (background.width, background.height)
 */

const bg = new Sprite(Texture.WHITE);
bg.width = width;
bg.height = height;
bg.tint = 'black';
bg.position={x:0,y:0}

container.addChild(bg);

// Groupe des particules
const particles = [];

//const particleCount = 100; // Réduire ce nombre

const clusters = [
    { keyword: "IA", x: bg.width * 0.3, y: bg.height * 0.5, color: '0xffaa00' },
    { keyword: "Éducation", x: bg.width * 0.5, y: bg.height * 0.3, color: '0x00aaff' },
    { keyword: "Éthique", x: bg.width * 0.6, y: bg.height * 0.2, color: '0xff5555' },
];

// Créer les clusters
clusters.forEach(cluster => {

    const wrappeur= new Container();
    // Texte pour le cluster
    const text = new Text({
        text: cluster.keyword,
        style: {
            fontFamily: "Arial",
            fontSize: 24,
            fill: cluster.color,
        },
        position: {x: cluster.x, y: cluster.y},
    });


    // Particules associées au cluster
    for (let i = 0; i < 50; i++) {
        const particle = new Graphics();
        particle.circle(0, 0, 2);
        particle.fill(0xde3249);
        particle.x = cluster.x + Math.random() * 100 - 50;
        particle.y = cluster.y + Math.random() * 100 - 50;
        particle.vx = Math.random() * 2 - 1;
        particle.vy = Math.random() * 2 - 1;
        wrappeur.addChild(particle);
        particles.push({ particle, cluster });
    }
    wrappeur.addChild(text);
    container.addChild(wrappeur);
});


/*
particles.forEach(({ particle }) => {
    if (particle.x < 0 || particle.x > width || particle.y < 0 || particle.y > height) {
        console.warn("Particule hors du cadre :", particle.x, particle.y);
    }
});

 */

// Animation des particules
app.ticker.add(() => {

    particles.forEach(({ particle, cluster }) => {
        particle.x += particle.vx;
        particle.y += particle.vy;

       // console.log(particle.x, particle.y); // Vérifier les coordonnées

        /*
                if (performance.now() % 2 === 0) { // Exécuter à 30 FPS
                    particles.forEach(p => {
                        p.x += p.vx;
                        p.y += p.vy;
                    });
                }

         */

        // Retour aux limites du cluster
        if (Math.abs(particle.x - cluster.x) > 100) {
            particle.vx *= -1;
        }
        if (Math.abs(particle.y - cluster.y) > 100) {
            particle.vy *= -1;
        }
        /*
        if (Math.abs(particle.x - cluster.x) > 200 || Math.abs(particle.y - cluster.y) > 200) {
            particle.vx = 0; // Arrêter le mouvement en cas de dépassement
            particle.vy = 0;
        }

         */
    });
});
