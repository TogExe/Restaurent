const canvas = document.getElementById("gameCanvas");
const ctx = canvas.getContext("2d");

let W, H;
function resize() {
    W = canvas.width = window.innerWidth;
    H = canvas.height = window.innerHeight;
}
window.addEventListener("resize", resize);
resize();

const keys = {};
window.addEventListener("keydown", e => keys[e.code] = true);
window.addEventListener("keyup", e => keys[e.code] = false);

/* ================= PHYSICS ================= */
class Point {
    constructor(x, y) {
        this.x = x; this.y = y;
        this.oldX = x; this.oldY = y;
    }
    update() {
        let vx = (this.x - this.oldX) * 0.95;
        let vy = (this.y - this.oldY) * 0.95;
        this.oldX = this.x; this.oldY = this.y;
        this.x += vx;
        this.y += vy + 1.2; 
    }
}

class Bone {
    constructor(p1, p2, len) {
        this.p1 = p1; this.p2 = p2; this.len = len;
    }
    resolve() {
        let dx = this.p2.x - this.p1.x;
        let dy = this.p2.y - this.p1.y;
        let dist = Math.hypot(dx, dy);
        if (dist === 0) return;
        let diff = (this.len - dist) / dist;
        this.p1.x -= dx * diff * 0.5;
        this.p1.y -= dy * diff * 0.5;
        this.p2.x += dx * diff * 0.5;
        this.p2.y += dy * diff * 0.5;
    }
}

/* ================= STEEP TERRAIN ================= */
class TerrainLayer {
    constructor(seed, base, amp, color, speed, complexity) {
        this.seed = seed;
        this.base = base;
        this.amp = amp;
        this.color = color;
        this.speed = speed;
        this.complexity = complexity;
    }
    getHeight(x) {
        // High power creates flat valleys and sudden steep peaks
        let v = Math.pow(Math.sin(x * 0.0003 + this.seed) * 0.5 + 0.5, 3.5);
        let m = Math.sin(x * 0.001 + this.seed) * this.amp;
        let h = Math.sin(x * 0.004 + this.seed) * (this.amp * 0.3);
        return H - this.base - (m + h) * (v * this.complexity);
    }
    draw(camX, camY) {
        ctx.fillStyle = this.color;
        ctx.beginPath();
        ctx.moveTo(0, H);
        for (let x = 0; x <= W; x += 12) {
            ctx.lineTo(x, this.getHeight(x + camX * this.speed) - camY * this.speed);
        }
        ctx.lineTo(W, H);
        ctx.fill();
    }
}

const layers = [
    new TerrainLayer(123, 400, 500, "#1a1a2e", 0.2, 1.5),
    new TerrainLayer(456, 250, 300, "#16213e", 0.5, 2.0),
    new TerrainLayer(789, 100, 250, "#080808", 1.0, 3.5) 
];

/* ================= STICKMAN ================= */
class Stickman {
    constructor() {
        this.x = 200; this.y = 200;
        this.vx = 0; this.facing = 1;
        this.vy = 0;
        this.run = 0;
        this.onGround = false;

        this.hip = new Point(this.x, this.y);
        this.head = new Point(this.x, this.y - 65);
        this.neck = new Point(this.x, this.y - 50);
        this.kneeL = new Point(this.x, this.y + 35);
        this.ankleL = new Point(this.x, this.y + 70);
        this.kneeR = new Point(this.x, this.y + 35);
        this.ankleR = new Point(this.x, this.y + 70);
        this.elbowL = new Point(this.x, this.y - 25);
        this.handL = new Point(this.x, this.y);
        this.elbowR = new Point(this.x, this.y - 25);
        this.handR = new Point(this.x, this.y);

        this.points = [this.hip, this.head, this.neck, this.kneeL, this.ankleL, this.kneeR, this.ankleR, this.elbowL, this.handL, this.elbowR, this.handR];
        this.bones = [
            new Bone(this.hip, this.neck, 45), new Bone(this.neck, this.head, 18),
            new Bone(this.hip, this.kneeL, 38), new Bone(this.kneeL, this.ankleL, 38),
            new Bone(this.hip, this.kneeR, 38), new Bone(this.kneeR, this.ankleR, 38),
            new Bone(this.neck, this.elbowL, 26), new Bone(this.elbowL, this.handL, 26),
            new Bone(this.neck, this.elbowR, 26), new Bone(this.elbowR, this.handR, 26)
        ];
    }

    update() {
        let gH = layers[2].getHeight(this.x);
        let speed = Math.abs(this.vx);
        this.onGround = (this.hip.y >= gH - 85);

        // Control
        let accel = this.onGround ? 0.7 : 0.2;
        if (keys.ArrowLeft) { this.vx -= accel; this.facing = -1; }
        if (keys.ArrowRight) { this.vx += accel; this.facing = 1; }
        this.vx *= 0.93;
        this.x += this.vx;
        this.y +=this.vy

        // Jump (restored stable vector)
        if (keys.Space && this.onGround) {
            this.hip.oldY = this.hip.y + 4;
            this.vy+=3;
            this.onGround = false;
        }

        this.run = this.x * 0.015;

        if (this.onGround) {
            this.y = gH - 75;
            this.hip.x = this.x;
            this.hip.y = this.y;
        } else {
            this.y = gH - 75;

            this.hip.y=this.y;
            this.x = this.hip.x;
        }

        // --- ACCIDENTALLY MADE THIS ANIMATION AND ILL ALWAYS LOVE IT ---
        let stride = 50 + speed * 3.2;
        let lift = 2 + speed * 1.5;

        // Ankle targets
        let tALX = this.x + Math.sin(this.run) * stride;
        let tALY = layers[2].getHeight(tALX) - Math.max(0, Math.cos(this.run) * lift);
        let tARX = this.x + Math.sin(this.run + Math.PI) * stride;
        let tARY = layers[2].getHeight(tARX) - Math.max(0, Math.cos(this.run + Math.PI) * lift);

        // Arm targets
        let tHLX = this.x - Math.sin(this.run) * (stride * 0.8);
        let tHLY = this.neck.y + 25 + Math.cos(this.run) * 10;
        let tHRX = this.x - Math.sin(this.run + Math.PI) * (stride * 0.8);
        let tHRY = this.neck.y + 25 + Math.cos(this.run + Math.PI) * 10;

        const muscle = this.onGround ? 0.8 : 0.1; 
        
        [this.ankleL, this.ankleR, this.handL, this.handR].forEach((p, i) => {
            let tx = [tALX, tARX, tHLX, tHRX][i];
            let ty = [tALY, tARY, tHLY, tHRY][i];
            p.x += (tx - p.x) * muscle;
            p.y += (ty - p.y) * muscle;
        });

        // The "Knee Forward" thrust
        this.kneeL.x += this.facing * 15 * muscle;
        this.kneeR.x += this.facing * 15 * muscle;

        // --- SOLVER & STRENGTH ---
        this.points.forEach(p => {
            p.update();
            let h = layers[2].getHeight(p.x);
            if (p.y > h) { p.y = h; p.oldY = h + (p.y - p.oldY) * 0.4; }
        });

        for (let i = 0; i < 30; i++) {
            this.bones.forEach(b => b.resolve());
            // Torso constraint
            let dx = this.neck.x - this.hip.x;
            let dy = this.neck.y - this.hip.y;
            let d = Math.hypot(dx, dy);
            if(d > 50) {
                this.neck.x = this.hip.x + (dx/d)*50;
                this.neck.y = this.hip.y + (dy/d)*50;
            }
        }

        if (this.onGround) {
            this.head.x = this.hip.x;
            this.head.y = this.hip.y - 68;
            this.neck.x = this.hip.x;
            this.neck.y = this.hip.y - 50;
        }
    }

    draw(cX, cY) {
        ctx.strokeStyle = "white"; ctx.lineWidth = 9; ctx.lineCap = "round";
        this.bones.forEach(b => {
            ctx.beginPath(); 
            ctx.moveTo(b.p1.x - cX, b.p1.y - cY); 
            ctx.lineTo(b.p2.x - cX, b.p2.y - cY); 
            ctx.stroke();
        });
        ctx.fillStyle = "white"; ctx.beginPath(); 
        ctx.arc(this.head.x - cX, this.head.y - cY, 14, 0, Math.PI * 2); ctx.fill();
    }
}

/* ================= LOOP ================= */
const player = new Stickman();
let camX = 0, camY = 0;

function loop() {
    ctx.fillStyle = "#111"; ctx.fillRect(0, 0, W, H);
    
    // XY Camera
    camX += (player.x - W / 2 - camX) * 0.1;
    camY += (player.hip.y - H / 1.5 - camY) * 0.05;

    layers.forEach(l => l.draw(camX, camY));
    player.update();
    player.draw(camX, camY);

    requestAnimationFrame(loop);
}
loop();

