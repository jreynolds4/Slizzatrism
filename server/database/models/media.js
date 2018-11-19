const mongoose = require('mongoose')
const Schema = mongoose.Schema
mongoose.promise = Promise

// Define userSchema
const mediaSchema = new Schema({

	url: { type: String, unique: false, required: true },
    title: { type: String, unique: false, required: true },
    artists: { type: String, unique: false, required: true },
    description: { type: String, unique: false, required: true },
    platform: { type: String, unique: false, required: true }

})

// Define hooks for pre-saving
mediaSchema.pre('save', function (next) {
    console.log('models/media.js in pre save');
    next()
})

const Media = mongoose.model('Media', mediaSchema)
module.exports = Media